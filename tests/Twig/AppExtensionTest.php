<?php


namespace App\Tests\Twig;


use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

class AppExtensionTest extends TestCase
{
    /**
     * Simple Unit test
     */
    public function testToMonth()
    {
        $data = new AppExtension();

        $this->assertEquals('January', $data->toMonth(1));
    }
}