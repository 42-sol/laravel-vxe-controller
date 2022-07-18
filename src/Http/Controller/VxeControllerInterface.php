<?php

namespace VxeController\Http\Controller;

interface VxeControllerInterface {
    /**
     * Link model with controller
     * 
     * @return string
     */
    public function model(): string;
}