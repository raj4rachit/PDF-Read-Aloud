<?php

use App\Http\Controllers\PDFController;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {
    return view('welcome');
});


Route::get('/', [PDFController::class, 'index'])->name('pdf.index');
Route::post('/upload', [PDFController::class, 'upload'])->name('pdf.upload');
Route::get('/pdf/{id}', [PDFController::class, 'show'])->name('pdf.show');
Route::get('/pdf/{id}/read', [PDFController::class, 'read'])->name('pdf.read');
Route::delete('/pdf/{id}', [PdfController::class, 'destroy'])->name('pdf.delete');
