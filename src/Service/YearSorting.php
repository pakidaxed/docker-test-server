<?php


namespace App\Service;


class YearSorting
{
    private $sorted = [];

    /**
     * Sorting data from API or DB, and sorting it to 12 months separately
     * and returning data in array
     *
     * @param $data
     * @return array
     */
    public function sortingToMonths($data)
    {

        foreach ($data as $days) {
            if ($days['date']['month']) {
                $this->sorted[$days['date']['month']][] = $days;
            }
        }
        return $this->sorted;
    }
}