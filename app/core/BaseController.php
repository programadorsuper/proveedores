<?php
class BaseController
{
    protected function render($view, $data = [], $layout = 'main')
    {
        extract($data);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        $layoutFile = __DIR__ . '/../views/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            ob_start();
            if (file_exists($viewFile)) {
                include $viewFile;
            } else {
                echo "<p>Vista no encontrada: $viewFile</p>";
            }
            $content = ob_get_clean();
            include $layoutFile;
        } else {
            if (file_exists($viewFile)) {
                include $viewFile;
            } else {
                echo "<p>Vista no encontrada: $viewFile</p>";
            }
        }
    }
}
