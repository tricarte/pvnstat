<?php

declare(strict_types=1);

namespace Tricarte\Pvnstat\Helpers;

class Template {
    protected $viewVars;

    public function renderPage(string $tpl): bool {
        \ob_start();
        \extract($this->viewVars, \EXTR_SKIP);
        include 'views/' . $tpl;

        return \ob_end_flush();
    }

    public function assign(array $arr): self {
        foreach ($arr as $key => $value) {
            $this->viewVars[$key] = $value;
        }

        return $this;
    }
}
