<?php

namespace Doctrine\ODM\MongoDB\Query;

interface JavascriptInterface
{
    /**
     * Returns code to run the current query in the Javascript shell.
     *
     * @return string Javascript code
     */
    function toJavascript();
}
