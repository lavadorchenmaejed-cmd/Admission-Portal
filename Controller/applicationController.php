<?php

class ApplicationController {
    private $model;

    public function __construct(Application $model) {
        $this->model = $model;
    }

    public function store($application_id, $document, $filePath, $date) {
        $app = $this->model->create($application_id, $document, $filePath, $date);
    }
}

?>
