<?php

namespace App\Models;

class ObjectiveQuiz extends Record
{
    protected $hidden = ['answer_key'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'answer_key' => 'array'];
    }
}
