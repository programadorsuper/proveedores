<?php
require_once __DIR__ . '/../core/BaseController.php';
class InfoController extends BaseController
{
    public function publico()
    {
        $this->render('info/index', [
            'title' => 'Información Pública',
        ], 'public');
    }
}
