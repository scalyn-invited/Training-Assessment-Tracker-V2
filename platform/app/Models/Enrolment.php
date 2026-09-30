<?php

namespace App\Models;

class Enrolment extends Record
{
    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function files()
    {
        return $this->hasMany(EvidenceFile::class);
    }
}
