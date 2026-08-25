<?php
namespace OPNsense\HelloWorld;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // Define o arquivo de visualização (views/index.volt)
        $this->view->pick('OPNsense/HelloWorld/index');
    }
}