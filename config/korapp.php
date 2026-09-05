<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña temporal de nuevos usuarios
    |--------------------------------------------------------------------------
    |
    | Se asigna al crear un usuario desde Seguridad → Usuarios.
    | Mientras la use, el sistema obliga a cambiarla antes de entrar.
    |
    */

    'default_user_password' => env('DEFAULT_USER_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Usuarios ocultos en la gestión
    |--------------------------------------------------------------------------
    |
    | Estos correos no aparecen en Seguridad → Usuarios ni en selectores de
    | asignación (vendedores / reasignación de cartera).
    |
    */

    'hidden_user_emails' => [
        'sauloandres@gmail.com',
    ],

];
