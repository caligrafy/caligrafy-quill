<?php
// QA-only controller, copied into application/ by qa/run.sh. Never shipped.
// A project's own global AIController: must not collide with the example Caligrafy\Controllers\AIController.

use Caligrafy\Controller;

class AIController extends Controller {

    public function mine()
    {
        echo 'QA-OWN-AICONTROLLER';
    }
}
