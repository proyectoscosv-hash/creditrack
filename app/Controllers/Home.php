<?php

namespace App\Controllers;

class Home extends BaseController
{
    /**
     * Render home landing page confirming session state, role, and country
     */
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error', 'Debe iniciar sesión para acceder al sistema');
        }

        $data = [
            'isLoggedIn'      => (bool) session()->get('isLoggedIn'),
            'codigo_empleado' => (string) session()->get('codigo_empleado'),
            'usuario'         => (string) session()->get('usuario'),
            'nombre'          => (string) session()->get('nombre'),
            'rol'             => (string) session()->get('rol'),
            'id_pais'         => (int) session()->get('id_pais'),
            'pais_nombre'     => (string) session()->get('pais_nombre'),
            'login_time'      => (int) session()->get('login_time'),
        ];

        $viewPath = APPPATH . 'Views/home.php';
        if (is_file($viewPath) && filesize($viewPath) > 0) {
            return view('home', $data);
        }

        // Interim fallback view confirming session status before M3 view deployment
        return '<!DOCTYPE html>'
            . '<html lang="es">'
            . '<head><meta charset="UTF-8"><title>CrediTrack - Inicio</title></head>'
            . '<body>'
            . '<h1>Confirmación de Inicio de Sesión Exitoso</h1>'
            . '<p>Estado de la sesión: Activa</p>'
            . '<p>Código Empleado: ' . esc($data['codigo_empleado']) . '</p>'
            . '<p>Usuario: ' . esc($data['usuario']) . '</p>'
            . '<p>Nombre: ' . esc($data['nombre']) . '</p>'
            . '<p>Rol: ' . esc($data['rol']) . '</p>'
            . '<p>País Asignado: ' . esc($data['pais_nombre']) . ' (ID: ' . esc((string) $data['id_pais']) . ')</p>'
            . '<p>Límite de Acceso: Acceso exclusivo a operaciones de ' . esc($data['pais_nombre']) . '.</p>'
            . '<p><a href="' . site_url('logout') . '">Cerrar Sesión</a></p>'
            . '</body>'
            . '</html>';
    }
}
