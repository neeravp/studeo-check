<?php

namespace Studeo\Check\Engine;

interface Rule
{
    public function name(): string;

    /**
     * @return list<Violation>
     */
    public function violations(SourceSet $sources): array;
}
