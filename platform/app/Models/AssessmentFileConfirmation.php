<?php

namespace App\Models;

class AssessmentFileConfirmation extends Record
{
    protected function casts(): array
    {
        return ['text' => 'encrypted'];
    }
}
