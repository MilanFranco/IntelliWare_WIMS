<?php
// Deprecated demo bypass endpoint neutralized for security
session_start();
header('Location: ../../signin.php');
exit();
