<?php
// QA-only controller, copied into application/ by qa/run.sh. Never shipped.

use Caligrafy\Controller;

class QaController extends Controller {

    public function upload()
    {
        $url = uploadFile($this->request->files('f'));
        return api(array('url' => $url));
    }

    public function books()
    {
        return api(array('ok' => true, 'id' => $this->request->fetch('id')));
    }

    // the home page with the install to home screen module
    public function install()
    {
        return view('default/index', array('install' => true));
    }

    // a PHP fatal error (class redeclared at compile time)
    public function fatal()
    {
        echo 'PARTIAL-PAGE';
        eval('class QaFatalTwice {} class QaFatalTwice {}');
    }

    // an uncaught Error (not an Exception, so the try/catch of web.php does not see it)
    public function boom()
    {
        qa_undefined_function_with_secret_name();
    }
}
