<?php

namespace LuceneSearchBundle\Configuration\Categories;

interface CategoriesInterface
{
    /**
     * @return array
     */
    public function getCategories(): array;
}