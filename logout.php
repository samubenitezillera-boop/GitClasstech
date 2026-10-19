<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: InicioSesion.html');
exit;
