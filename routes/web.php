<?php

declare(strict_types=1);

use App\Controllers\AlertsController;
use App\Controllers\AttendanceController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\ClassroomsController;
use App\Controllers\CoursesController;
use App\Controllers\DashboardController;
use App\Controllers\EnrollmentsController;
use App\Controllers\GradesController;
use App\Controllers\GroupsController;
use App\Controllers\ParamsController;
use App\Controllers\PaymentsController;
use App\Controllers\PeriodsController;
use App\Controllers\PortalController;
use App\Controllers\ProgramsController;
use App\Controllers\ReportsController;
use App\Controllers\RolesController;
use App\Controllers\StudentsController;
use App\Controllers\TeachersController;
use App\Controllers\TeachingController;
use App\Controllers\UsersController;

/** @var App\Core\Router $router */

// Autenticación
$router->get('/login', [AuthController::class, 'showLogin'], null, true);
$router->post('/login', [AuthController::class, 'login'], null, true);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/change-password', [AuthController::class, 'showChangePassword']);
$router->post('/change-password', [AuthController::class, 'changePassword']);

// Panel
$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index'], 'dashboard.view');

// Portal del estudiante y carga docente
$router->get('/portal', [PortalController::class, 'index'], 'portal.view');
$router->get('/teaching', [TeachingController::class, 'index'], 'grades.view');

// Alertas
$router->get('/alerts', [AlertsController::class, 'index'], 'alerts.view');
$router->post('/alerts/{id}/attend', [AlertsController::class, 'attend'], 'alerts.manage');

// Estudiantes
$router->get('/students', [StudentsController::class, 'index'], 'students.view');
$router->get('/students/create', [StudentsController::class, 'create'], 'students.manage');
$router->post('/students', [StudentsController::class, 'store'], 'students.manage');
$router->get('/students/{id}/edit', [StudentsController::class, 'edit'], 'students.manage');
$router->post('/students/{id}', [StudentsController::class, 'update'], 'students.manage');
$router->post('/students/{id}/guardians', [StudentsController::class, 'storeGuardian'], 'students.manage');
$router->post('/students/guardians/{id}/toggle', [StudentsController::class, 'toggleGuardian'], 'students.manage');
$router->get('/students/{id}', [StudentsController::class, 'show'], 'students.view');

// Docentes
$router->get('/teachers', [TeachersController::class, 'index'], 'teachers.view');
$router->get('/teachers/create', [TeachersController::class, 'create'], 'teachers.manage');
$router->post('/teachers', [TeachersController::class, 'store'], 'teachers.manage');
$router->get('/teachers/{id}/edit', [TeachersController::class, 'edit'], 'teachers.manage');
$router->post('/teachers/{id}', [TeachersController::class, 'update'], 'teachers.manage');
$router->get('/teachers/{id}', [TeachersController::class, 'show'], 'teachers.view');

// Programas, plan de estudios y prerrequisitos
$router->get('/programs', [ProgramsController::class, 'index'], 'programs.view');
$router->get('/programs/create', [ProgramsController::class, 'create'], 'programs.manage');
$router->post('/programs', [ProgramsController::class, 'store'], 'programs.manage');
$router->get('/programs/{id}/edit', [ProgramsController::class, 'edit'], 'programs.manage');
$router->post('/programs/{id}', [ProgramsController::class, 'update'], 'programs.manage');
$router->post('/programs/{id}/courses', [ProgramsController::class, 'addCourse'], 'programs.manage');
$router->post('/programs/{id}/courses/{planId}/remove', [ProgramsController::class, 'removeCourse'], 'programs.manage');
$router->post('/programs/{id}/prerequisites', [ProgramsController::class, 'addPrerequisite'], 'programs.manage');
$router->post('/programs/{id}/prerequisites/{prerequisiteId}/remove', [ProgramsController::class, 'removePrerequisite'], 'programs.manage');
$router->get('/programs/{id}', [ProgramsController::class, 'show'], 'programs.view');

// Cursos
$router->get('/courses', [CoursesController::class, 'index'], 'courses.view');
$router->get('/courses/create', [CoursesController::class, 'create'], 'courses.manage');
$router->post('/courses', [CoursesController::class, 'store'], 'courses.manage');
$router->get('/courses/{id}/edit', [CoursesController::class, 'edit'], 'courses.manage');
$router->post('/courses/{id}', [CoursesController::class, 'update'], 'courses.manage');
$router->get('/courses/{id}', [CoursesController::class, 'show'], 'courses.view');

// Periodos
$router->get('/periods', [PeriodsController::class, 'index'], 'periods.view');
$router->get('/periods/create', [PeriodsController::class, 'create'], 'periods.manage');
$router->post('/periods', [PeriodsController::class, 'store'], 'periods.manage');
$router->get('/periods/{id}/edit', [PeriodsController::class, 'edit'], 'periods.manage');
$router->post('/periods/{id}', [PeriodsController::class, 'update'], 'periods.manage');
$router->post('/periods/{id}/open-enrollment', [PeriodsController::class, 'openEnrollment'], 'periods.manage');
$router->post('/periods/{id}/start', [PeriodsController::class, 'start'], 'periods.manage');
$router->post('/periods/{id}/close', [PeriodsController::class, 'close'], 'periods.manage');
$router->get('/periods/{id}', [PeriodsController::class, 'show'], 'periods.view');

// Aulas
$router->get('/classrooms', [ClassroomsController::class, 'index'], 'classrooms.view');
$router->get('/classrooms/create', [ClassroomsController::class, 'create'], 'classrooms.manage');
$router->post('/classrooms', [ClassroomsController::class, 'store'], 'classrooms.manage');
$router->get('/classrooms/{id}/edit', [ClassroomsController::class, 'edit'], 'classrooms.manage');
$router->post('/classrooms/{id}', [ClassroomsController::class, 'update'], 'classrooms.manage');

// Grupos
$router->get('/groups', [GroupsController::class, 'index'], 'groups.view');
$router->get('/groups/create', [GroupsController::class, 'create'], 'groups.manage');
$router->post('/groups', [GroupsController::class, 'store'], 'groups.manage');
$router->get('/groups/{id}/edit', [GroupsController::class, 'edit'], 'groups.manage');
$router->post('/groups/{id}', [GroupsController::class, 'update'], 'groups.manage');
$router->get('/groups/{id}', [GroupsController::class, 'show'], 'groups.view');

// Matrículas e inscripciones
$router->get('/enrollments', [EnrollmentsController::class, 'index'], 'enrollment.view');
$router->get('/enrollments/create', [EnrollmentsController::class, 'create'], 'enrollment.manage');
$router->post('/enrollments', [EnrollmentsController::class, 'store'], 'enrollment.manage');
$router->post('/enrollments/inscriptions/{inscripcionId}/change-group', [EnrollmentsController::class, 'changeGroup'], 'enrollment.manage');
$router->post('/enrollments/inscriptions/{inscripcionId}/cancel', [EnrollmentsController::class, 'cancel'], 'enrollment.manage');
$router->post('/enrollments/{id}/enroll', [EnrollmentsController::class, 'enroll'], 'enrollment.manage');
$router->get('/enrollments/{id}', [EnrollmentsController::class, 'show'], 'enrollment.view');

// Calificaciones
$router->get('/grades', [GradesController::class, 'index'], 'grades.view');
$router->post('/grades/{groupId}/evaluations', [GradesController::class, 'storeEvaluation'], 'grades.manage');
$router->post('/grades/{groupId}/evaluations/{evaluationId}/deactivate', [GradesController::class, 'deactivateEvaluation'], 'grades.manage');
$router->post('/grades/{groupId}/evaluations/{evaluationId}/notas', [GradesController::class, 'saveGrades'], 'grades.manage');
$router->post('/grades/{groupId}/evaluations/{evaluationId}', [GradesController::class, 'updateEvaluation'], 'grades.manage');
$router->get('/grades/{groupId}/evaluations/{evaluationId}', [GradesController::class, 'sheet'], 'grades.view');
$router->get('/grades/{groupId}', [GradesController::class, 'show'], 'grades.view');

// Asistencia
$router->get('/attendance', [AttendanceController::class, 'index'], 'attendance.view');
$router->post('/attendance/{groupId}/sessions', [AttendanceController::class, 'storeSession'], 'attendance.manage');
$router->post('/attendance/{groupId}/sessions/{sessionId}', [AttendanceController::class, 'save'], 'attendance.manage');
$router->get('/attendance/{groupId}/sessions/{sessionId}', [AttendanceController::class, 'sheet'], 'attendance.view');
$router->get('/attendance/{groupId}', [AttendanceController::class, 'show'], 'attendance.view');

// Pagos y cartera
$router->get('/payments', [PaymentsController::class, 'index'], 'payments.view');
$router->get('/payments/history', [PaymentsController::class, 'history'], 'payments.view');
$router->get('/payments/create', [PaymentsController::class, 'create'], 'payments.manage');
$router->post('/payments', [PaymentsController::class, 'store'], 'payments.manage');
$router->get('/payments/receipts/{id}', [PaymentsController::class, 'show'], 'payments.view');
$router->post('/payments/receipts/{id}/annul', [PaymentsController::class, 'annul'], 'payments.manage');
$router->get('/payments/statement/{estudianteId}', [PaymentsController::class, 'statement'], 'payments.view');
$router->get('/payments/charges/create', [PaymentsController::class, 'createCharge'], 'payments.manage');
$router->post('/payments/charges', [PaymentsController::class, 'storeCharge'], 'payments.manage');
$router->post('/payments/charges/{id}/annul', [PaymentsController::class, 'annulCharge'], 'payments.manage');

// Informes
$router->get('/reports', [ReportsController::class, 'index'], 'reports.view');
$router->get('/reports/run', [ReportsController::class, 'run'], 'reports.view');
$router->get('/reports/export', [ReportsController::class, 'export'], 'reports.view');

// Usuarios
$router->get('/users', [UsersController::class, 'index'], 'users.manage');
$router->get('/users/create', [UsersController::class, 'create'], 'users.manage');
$router->post('/users', [UsersController::class, 'store'], 'users.manage');
$router->get('/users/{id}/edit', [UsersController::class, 'edit'], 'users.manage');
$router->post('/users/{id}/reset-password', [UsersController::class, 'resetPassword'], 'users.manage');
$router->post('/users/{id}', [UsersController::class, 'update'], 'users.manage');

// Roles y permisos
$router->get('/roles', [RolesController::class, 'index'], 'roles.manage');
$router->get('/roles/create', [RolesController::class, 'create'], 'roles.manage');
$router->post('/roles', [RolesController::class, 'store'], 'roles.manage');
$router->post('/roles/{id}/permissions', [RolesController::class, 'updatePermissions'], 'roles.manage');
$router->get('/roles/{id}/edit', [RolesController::class, 'edit'], 'roles.manage');
$router->post('/roles/{id}', [RolesController::class, 'update'], 'roles.manage');

// Parámetros
$router->get('/params', [ParamsController::class, 'index'], 'params.manage');
$router->post('/params', [ParamsController::class, 'update'], 'params.manage');

// Auditoría
$router->get('/audit', [AuditController::class, 'index'], 'audit.view');
