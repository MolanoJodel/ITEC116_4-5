<?php
// Run with: php tests/validation.php (CLI only, no database required).
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__.'/../app/bootstrap.php';
$checks = [
    'Valid student number' => validStudentNumber('2026-001'),
    'Reject invalid student number' => !validStudentNumber('x<script>'),
    'Accept 0 and 100 grade' => validGrade('0') && validGrade('100'),
    'Accept decimal grade' => validGrade('91.50'),
    'Reject out of range grades' => !validGrade('101') && !validGrade('-1'),
    'Reject malformed grade' => !validGrade('1e2') && !validGrade('12.345'),
    'Accept today' => validDate(date('Y-m-d')),
    'Reject future/invalid date' => !validDate(date('Y-m-d',strtotime('+1 day'))) && !validDate('2025-02-30'),
    'Reject short password' => !validPassword('short'),
    'Accept long password' => validPassword('A-Unique-Pass!2026'),
    'Reject excessive/null password' => !validPassword(str_repeat('x',73)) && !validPassword("longpassword\0"),
    'Escape HTML output' => e('<script>"') === '&lt;script&gt;&quot;',
    'Teacher seed hash' => password_verify('TeacherDemo!2026','$2y$12$xnjLbn5srAfi3JLADg9aWuRIwik7POhrwR.wdDI.eyj2AI6HdP1Q2'),
    'Student seed hash' => password_verify('StudentDemo!2026','$2y$12$642QnmzgtdpLnnpiq4d0fun5Wl/WeEEpJ5lFgJCPFxxjmsfqgEmju'),
];
foreach ($checks as $name=>$pass) echo ($pass?'PASS':'FAIL')." - $name\n";
exit(in_array(false,$checks,true)?1:0);
