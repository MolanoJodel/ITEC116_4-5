<?php
// All changes require POST, a valid CSRF token and the appropriate role.
function handlePost(?array $user): never {
    if (!hash_equals($_SESSION['csrf'], post('csrf'))) abortRequest(403,'Invalid or expired form token. Reload the page and try again.');
    $action = post('action');
    if ($action === 'login') {
        if ($user) redirect();
        $username = strtolower(trim(post('username')));
        $password = post('password');
        if (!validStudentNumber($username) || strlen($password)>72 || str_contains($password,"\0")) {
            flash('Invalid username or password.','error'); redirect('login');
        }
        // Fixed-window, persistent throttling by username and source IP.
        $keys = [hash('sha256','account:'.$username),hash('sha256','ip:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'))];
        // Reserve an attempt before password verification, atomically per key.
        foreach ($keys as $key) {
            query('INSERT INTO login_limits (limit_key,attempts,window_start) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_start < NOW()-INTERVAL 15 MINUTE,1,attempts+1),window_start=IF(window_start < NOW()-INTERVAL 15 MINUTE,NOW(),window_start)',[$key]);
        }
        $limits = all('SELECT limit_key,attempts FROM login_limits WHERE limit_key IN (?,?)',$keys);
        foreach ($limits as $limit) {
            $max = $limit['limit_key']===$keys[0] ? 8 : 40;
            if ((int)$limit['attempts']>$max) { flash('Too many sign-in attempts. Try again in 15 minutes.','error'); redirect('login'); }
        }
        $account = one('SELECT * FROM users WHERE username=?',[$username]);
        // Dummy hash prevents an obvious timing difference for unknown usernames.
        $verified = password_verify($password,$account['password_hash'] ?? '$2y$12$bx0NfY3MPTqtjglRilfPfeX6rvmec/mZxgCzIc4knLjo6iyPSgVea');
        if (!$account || !$verified) { flash('Invalid username or password.','error'); redirect('login'); }
        query('DELETE FROM login_limits WHERE limit_key=?',[$keys[0]]);
        // IP counter is kept so repeated attempts across accounts remain limited.
        if (password_needs_rehash($account['password_hash'],PASSWORD_DEFAULT)) query('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?',[password_hash($password,PASSWORD_DEFAULT),$account['id'],$account['password_hash']]);
        clearSession();
        $_SESSION['userId']=(int)$account['id']; $_SESSION['authVersion']=(int)$account['auth_version']; $_SESSION['lastActivity']=time(); $_SESSION['csrf']=bin2hex(random_bytes(32));
        flash('Signed in successfully.'); redirect($account['must_change'] ? 'account' : 'dashboard');
    }
    if (!$user) abortRequest(401,'Please sign in first.');
    if ($action === 'logout') {
        clearSession(); $_SESSION['csrf']=bin2hex(random_bytes(32)); flash('You have been signed out.'); redirect('login');
    }
    if ($action === 'changePassword') {
        try {
            $old=post('current_password'); $new=post('new_password');
            $row=one('SELECT password_hash FROM users WHERE id=?',[$user['id']]);
            if (strlen($old)>72 || str_contains($old,"\0") || !password_verify($old,$row['password_hash'])) throw new DomainException('Your current password is incorrect.');
            if (!validPassword($new) || $new!==post('confirm_password') || $new===$old) throw new DomainException('Choose a different password, 12–72 bytes, and confirm it correctly.');
            query('UPDATE users SET password_hash=?,must_change=0,auth_version=auth_version+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$user['id']]);
            session_regenerate_id(true); $_SESSION['authVersion']=(int)$user['auth_version']+1; $_SESSION['csrf']=bin2hex(random_bytes(32)); flash('Password updated. Other sessions have been invalidated.'); redirect();
        } catch (DomainException $ex) { flash($ex->getMessage(),'error'); redirect('account'); }
    }
    if ($user['must_change']) abortRequest(403,'Change your temporary password first.');
    requireRole($user,'teacher');
    $teacherId=(int)$user['id'];
    $fallback = match($action) { 'saveStudent','resetPassword'=>'students','saveAttendance'=>'attendance','saveGrade'=>'grades',default=>'dashboard' };
    try {
        switch ($action) {
            case 'saveStudent':
                $studentNumber=textField('student_number',32,'student ID');
                if (!validStudentNumber($studentNumber)) throw new DomainException('Student ID must have 3–32 letters, numbers, hyphens or underscores.');
                $name=textField('display_name',120,'student name'); $section=textField('section',60,'class / section');
                $id=post('student_id') === '' ? null : intId(post('student_id'));
                $existing=$id ? ownedStudent($id,$teacherId) : null;
                if (!$id && !validPassword(post('password'))) throw new DomainException('Temporary password must contain 12–72 bytes.');
                db()->beginTransaction();
                if ($existing) {
                    query('UPDATE users SET display_name=?,username=? WHERE id=?',[$name,strtolower($studentNumber),$existing['user_id']]);
                    query('UPDATE students SET student_number=?,section=? WHERE id=? AND teacher_id=?',[$studentNumber,$section,$id,$teacherId]);
                } else {
                    query("INSERT INTO users (username,password_hash,role,display_name,must_change) VALUES (?,?,'student',?,1)",[strtolower($studentNumber),password_hash(post('password'),PASSWORD_DEFAULT),$name]);
                    $userId=(int)db()->lastInsertId();
                    query('INSERT INTO students (user_id,teacher_id,student_number,section) VALUES (?,?,?,?)',[$userId,$teacherId,$studentNumber,$section]);
                }
                db()->commit(); flash($id?'Student information updated.':'Student account created. Share the temporary password privately.'); redirect('students');
            case 'resetPassword':
                $student=ownedStudent(intId(post('student_id')),$teacherId);
                if (!validPassword(post('password'))) throw new DomainException('Temporary password must contain 12–72 bytes.');
                query('UPDATE users SET password_hash=?,must_change=1,auth_version=auth_version+1 WHERE id=?',[password_hash(post('password'),PASSWORD_DEFAULT),$student['user_id']]);
                flash('Temporary password set. Existing student sessions were invalidated.'); redirect('students',['edit'=>$student['id']]);
            case 'saveAttendance':
                $id=intId(post('student_id')); ownedStudent($id,$teacherId);
                $date=post('attendance_date'); $status=post('status');
                if (!validDate($date)) throw new DomainException('Select a valid date that is not in the future.');
                if (!in_array($status,['Present','Absent'],true)) throw new DomainException('Select Present or Absent.');
                query('INSERT INTO attendance (student_id,attendance_date,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)',[$id,$date,$status]);
                flash('Attendance saved. An existing record for this date was updated.'); redirect('attendance',['student'=>$id]);
            case 'saveGrade':
                $id=intId(post('student_id')); ownedStudent($id,$teacherId);
                $subject=textField('subject',100,'subject'); $period=post('period'); $grade=post('grade');
                if (!in_array($period,['Prelim','Midterm','Final'],true)) throw new DomainException('Choose a valid grading period.');
                if (!validGrade($grade)) throw new DomainException('Grade must be between 0 and 100 (up to two decimal places).');
                query('INSERT INTO grades (student_id,subject,period,grade) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE grade=VALUES(grade)',[$id,$subject,$period,$grade]);
                flash('Grade saved. An existing subject and period was updated.'); redirect('grades',['student'=>$id]);
            default: abortRequest(400,'Unknown action.');
        }
    } catch (DomainException $ex) {
        if (db()->inTransaction()) db()->rollBack(); flash($ex->getMessage(),'error'); redirect($fallback);
    } catch (PDOException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        if (($ex->errorInfo[1] ?? 0) === 1062) { flash('That student ID or username is already in use.','error'); redirect($fallback); }
        throw $ex;
    }
}
