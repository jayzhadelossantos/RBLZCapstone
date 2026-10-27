<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisposalController;
use App\Http\Controllers\PropertyRequestController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\Teacher\TeacherPropertyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/qr/{token}', [QrCodeController::class, 'scan'])->name('qr.scan');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/register/verify', [RegisteredUserController::class, 'showVerificationForm'])->name('register.verify');
    Route::post('/register/verify', [RegisteredUserController::class, 'verify'])->name('register.verify.store');
    Route::post('/register/resend-otp', [RegisteredUserController::class, 'resendOtp'])->name('register.resend-otp');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/property-requests/{propertyRequest}/receipt', [PropertyRequestController::class, 'receipt'])->name('property-requests.receipt');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');

        Route::resource('users', UserController::class)->except(['show']);
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::patch('/disposals/{disposal}/status', [DisposalController::class, 'updateStatus'])->name('disposals.status');
    });

    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/property-requests', [PropertyRequestController::class, 'index'])->name('property-requests.index');
        Route::get('/property-requests/{propertyRequest}', [PropertyRequestController::class, 'show'])->name('property-requests.show');
        Route::patch('/property-requests/{propertyRequest}/status', [PropertyRequestController::class, 'updateStatus'])->name('property-requests.status');
        Route::resource('properties', PropertyController::class);
        Route::resource('assignments', AssignmentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::get('/assignments/{assignment}/print', [AssignmentController::class, 'print'])->name('assignments.print');
        Route::patch('/assignments/{assignment}/return', [AssignmentController::class, 'markReturned'])->name('assignments.return');
        Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::resource('disposals', DisposalController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/qr-codes', [QrCodeController::class, 'index'])->name('qr.index');
        Route::get('/properties/{property}/qr', [QrCodeController::class, 'show'])->name('qr.show');
        Route::get('/properties/{property}/qr/print', [QrCodeController::class, 'print'])->name('qr.print');
        Route::get('/properties/{property}/qr/download', [QrCodeController::class, 'download'])->name('qr.download');
        Route::get('/property-units/{propertyUnit}/qr', [QrCodeController::class, 'showUnit'])->name('qr.units.show');
        Route::get('/property-units/{propertyUnit}/qr/print', [QrCodeController::class, 'printUnit'])->name('qr.units.print');
        Route::get('/property-units/{propertyUnit}/qr/download', [QrCodeController::class, 'downloadUnit'])->name('qr.units.download');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/properties', [ReportController::class, 'properties'])->name('reports.properties');
        Route::get('/reports/assignments', [ReportController::class, 'assignments'])->name('reports.assignments');
        Route::get('/reports/disposals', [ReportController::class, 'disposals'])->name('reports.disposals');
        Route::get('/reports/properties/pdf', [ReportController::class, 'propertiesPdf'])->name('reports.properties.pdf');
        Route::get('/reports/assignments/pdf', [ReportController::class, 'assignmentsPdf'])->name('reports.assignments.pdf');
        Route::get('/reports/disposals/pdf', [ReportController::class, 'disposalsPdf'])->name('reports.disposals.pdf');
        Route::get('/reports/properties/csv', [ReportController::class, 'propertiesCsv'])->name('reports.properties.csv');
        Route::get('/reports/assignments/csv', [ReportController::class, 'assignmentsCsv'])->name('reports.assignments.csv');
        Route::get('/reports/disposals/csv', [ReportController::class, 'disposalsCsv'])->name('reports.disposals.csv');
    });

    Route::middleware('role:staff')->group(function () {
        Route::get('/staff/dashboard', [DashboardController::class, 'staff'])->name('staff.dashboard');
    });

    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/dashboard', [TeacherPropertyController::class, 'dashboard'])->name('teacher.dashboard');
        Route::get('/teacher/property-requests', [PropertyRequestController::class, 'teacherIndex'])->name('teacher.property-requests.index');
        Route::get('/teacher/property-requests/create', [PropertyRequestController::class, 'create'])->name('teacher.property-requests.create');
        Route::post('/teacher/property-requests', [PropertyRequestController::class, 'store'])->name('teacher.property-requests.store');
        Route::get('/teacher/my-properties', [TeacherPropertyController::class, 'index'])->name('teacher.properties.index');
        Route::get('/teacher/my-properties/print', [TeacherPropertyController::class, 'print'])->name('teacher.properties.print');
        Route::get('/teacher/my-properties/{property}', [TeacherPropertyController::class, 'show'])->name('teacher.properties.show');
    });
});
