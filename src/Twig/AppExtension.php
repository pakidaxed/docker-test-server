<?php


namespace App\Twig;


use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('toMonth', [$this, 'toMonth']),
        ];
    }

    /**
     * Converting from number to full month name for easier display in Twig
     *
     * @param int $int
     * @return false|string
     */
    public function toMonth(int $int)
    {
        return date('F', mktime(0, 0, 0, $int, 10));
    }
}