<?php
class SampleService
{
    public function process(array $items): void
    {
        foreach ($items as $item) {
            foreach ($item['rows'] as $row) {
                if ($row['active'] && $row['value'] > 10) {
                    $this->query($row['id']);
                }
            }
        }
    }

    public function small(int $value): bool
    {
        if ($value > 10) return true;
        return false;
    }
}
