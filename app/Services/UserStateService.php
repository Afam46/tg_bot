<?php

namespace App\Services;

class UserStateService
{
    public function setState($user, $state)
    {   
        if(method_exists($user, 'save')){
            $user->state = $state;
            $user->save();
        }
    }
}
