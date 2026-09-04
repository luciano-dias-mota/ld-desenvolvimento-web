<?php

/** @var \App\Core\Router $router */

// Home
$router->get('/', 'HomeController@index');

// Auth
$router->get('/login', 'AuthController@showLoginForm')->middleware('guest');
$router->post('/login', 'AuthController@login')->middleware('guest');
$router->get('/register', 'AuthController@showRegisterForm')->middleware('guest');
$router->post('/register', 'AuthController@register')->middleware('guest');
$router->post('/logout', 'AuthController@logout')->middleware('auth');

// Dashboard (mapa da jornada)
$router->get('/dashboard', 'DashboardController@index')->middleware('auth');

// Cursos
$router->get('/cursos/{courseSlug}/{moduleSlug}', 'CourseController@showModule')->middleware('auth');
$router->get('/cursos/{courseSlug}/{moduleSlug}/prova', 'ModuleTestController@show')->middleware('auth');
$router->post('/cursos/{courseSlug}/{moduleSlug}/prova', 'ModuleTestController@submit')->middleware('auth');

// Aulas
$router->get('/aulas/{courseSlug}/{moduleSlug}/{lessonSlug}', 'LessonController@show')->middleware('auth');
$router->post('/aulas/{courseSlug}/{moduleSlug}/{lessonSlug}/concluir', 'LessonController@complete')->middleware('auth');

// Exercícios
$router->get('/exercicios/{courseSlug}/{moduleSlug}/{lessonSlug}', 'ExerciseController@show')->middleware('auth');
$router->post('/exercicios/{courseSlug}/{moduleSlug}/{lessonSlug}', 'ExerciseController@submit')->middleware('auth');

// Certificado
$router->get('/certificado/{courseSlug}', 'CertificateController@show')->middleware('auth');

// Validação de certificado (pública)
$router->get('/certificado/validar/{code}', 'CertificateController@validate');

// Página 404 (tratada pelo ErrorController)