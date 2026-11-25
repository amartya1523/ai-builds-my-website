<?php

use App\Http\Controllers\PublishController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'index'])->name('sites.index');
Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
Route::post('/sites/{site}/sources', [SiteController::class, 'addSource'])->name('sites.sources');
Route::post('/sites/{site}/rebuild', [SiteController::class, 'rebuild'])->name('sites.rebuild');

Route::post('/sites/{site}/questions/{question}', [QuestionController::class, 'answer'])->name('questions.answer');

Route::put('/sites/{site}/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
Route::post('/sites/{site}/sections/{section}/regenerate', [SectionController::class, 'regenerate'])->name('sections.regenerate');
Route::post('/sites/{site}/sections/{section}/toggle', [SectionController::class, 'toggle'])->name('sections.toggle');
Route::post('/sites/{site}/sections/{section}/move', [SectionController::class, 'move'])->name('sections.move');

Route::get('/sites/{site}/preview', [PublishController::class, 'preview'])->name('sites.preview');
Route::post('/sites/{site}/publish', [PublishController::class, 'publish'])->name('sites.publish');
Route::post('/sites/{site}/unpublish', [PublishController::class, 'unpublish'])->name('sites.unpublish');

// Public, published websites.
Route::get('/s/{slug}', [PublishController::class, 'show'])->name('public.site');
