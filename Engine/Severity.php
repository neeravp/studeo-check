<?php

namespace Studeo\Check\Engine;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
