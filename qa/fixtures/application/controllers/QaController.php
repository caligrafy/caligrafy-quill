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
}
