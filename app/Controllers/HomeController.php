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
            'stats'        => $model->getDashboardStats(),
            'entradas'     => $model->getUltimasEntradas(),
            'alertasStock' => $model->getAlertasStock(10), 
        ];

        return view('dashboard', $data);
    }
<<<<<<< HEAD
    
}
=======
}
>>>>>>> a25ae455709c093f4c2148c5e025674248c5ac8f
