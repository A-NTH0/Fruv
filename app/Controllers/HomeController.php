<?php

namespace App\Controllers;

use App\Models\ProductoModel;

class HomeController extends BaseController
{
    public function index()
    {
        helper('url');
        $model = new ProductoModel();

        $data = [
            'stats'    => $model->getDashboardStats(),
            'entradas' => $model->getUltimasEntradas(),
        ];

        return view('dashboard', $data);
    }
}
