<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total
    ) {
    }

    public static function fromRequest(int $perPage = 20): self
    {
        $page = max(1, Request::int('page', 1));

        return new self($page, max(1, $perPage), max(0, Request::int('total', 0)));
    }

    public static function of(int $page, int $perPage, int $total): self
    {
        return new self(max(1, $page), max(1, $perPage), max(0, $total));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->totalPages() > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /** @return list<int> */
    public function window(int $radius = 2): array
    {
        $start = max(1, $this->page - $radius);
        $end = min($this->totalPages(), $this->page + $radius);
        $pages = [];

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        return $pages;
    }
}
