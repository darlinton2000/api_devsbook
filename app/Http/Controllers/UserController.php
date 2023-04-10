<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    private $loggedUser;
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    /**
     * Faz a atualizacao dos dados do usuario
     * @param Request $request
     * @return string[]
     */
    public function update(Request $request)
    {
        $array = ['error' => ''];

        // Recebendo os dados
        $name             = $request->input('name');
        $email            = $request->input('email');
        $birthdate        = $request->input('birthdate');
        $city             = $request->input('city');
        $work             = $request->input('work');
        $password         = $request->input('password');
        $password_confirm = $request->input('password_confirm');

        $user = User::find($this->loggedUser['id']);

        // Name
        if ($name) {
            $user->name = $name;
        }

        // E-mail
        if ($email) {
            if ($email != $user->email) {
                $emailExist = User::where('email', $email)->count();
                if ($emailExist === 0) {
                    $user->email = $email;
                } else {
                    $array['error'] = 'E-mail ja existe!';
                    return $array;
                }
            }
        }

        // Birthdate
        if ($birthdate) {
            if (strtotime($birthdate) === false) {
                $array['error'] = 'Data de nascimento invalida!';
                return $array;
            }
            $user->birthdate = $birthdate;
        }

        // City
        if ($city) {
            $user->city = $city;
        }

        // Work
        if ($work) {
            $user->work = $work;
        }

        // Password
        if ($password && $password_confirm) {
            if ($password === $password_confirm) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $user->password = $hash;
            } else {
                $array['error'] = 'As senhas nao batem.';
                return $array;
            }
        }

        $user->save();

        return $array;
    }
}
