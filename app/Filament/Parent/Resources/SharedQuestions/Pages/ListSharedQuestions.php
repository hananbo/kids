<?php

namespace App\Filament\Parent\Resources\SharedQuestions\Pages;

use App\Filament\Parent\Resources\SharedQuestions\SharedQuestionResource;
use Filament\Resources\Pages\ListRecords;

class ListSharedQuestions extends ListRecords
{
    protected static string $resource = SharedQuestionResource::class;
}
