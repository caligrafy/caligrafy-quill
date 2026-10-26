<?php
// QA-only controller, copied into application/ by qa/run.sh. Never shipped.
// A namespaced application controller, routed as 'Shop\OrderController@show'.

namespace Shop;

use Caligrafy\Controller;

class OrderController extends Controller {

    public function show()
    {
        return api(array('controller' => static::class, 'id' => $this->request->fetch('id')));
    }
}
