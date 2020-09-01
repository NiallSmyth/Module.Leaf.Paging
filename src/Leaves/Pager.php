<?php

/*
 *	Copyright 2020 RhubarbPHP
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace Rhubarb\Leaf\Paging\Leaves;

use Rhubarb\Crown\Events\Event;
use Rhubarb\Crown\Request\WebRequest;
use Rhubarb\Leaf\Leaves\LeafModel;
use Rhubarb\Leaf\Leaves\UrlStateLeaf;
use Rhubarb\Leaf\Paging\Exceptions\PagerOutOfBoundsException;
use Rhubarb\Stem\Aggregates\Count;
use Rhubarb\Stem\Collections\Collection;

class Pager extends UrlStateLeaf
{
    /** @var Collection|null The collection to page */
    private $collection;

    /**
     * @var PagerModel
     */
    protected $model;

    /**
     * @var Event
     */
    public $pageChangedEvent;

    /**
     * Indicates whether or not the pager has changed the range of the collection.
     *
     * @var bool
     */
    public $collectionRangeModified = false;

    public function __construct(Collection $collection = null, $perPage = 50, $name = "")
    {
        parent::__construct($name);

        $this->pageChangedEvent = new Event();

        $this->collection = $collection;

        $this->model->perPage = $perPage;

        $this->pageChangedEvent->attachHandler(function ($pageNumber) {
            $this->setPageNumber($pageNumber);
        });

        $this->model->pageChangedEvent->attachHandler(function ($pageNumber) {
            $this->pageChangedEvent->raise($pageNumber);
        });
    }

    protected function onModelCreated()
    {
        parent::onModelCreated();

        $this->model->pageChangedEvent->attachHandler(function($pageNumber){
            $this->changePageNumber($pageNumber);
        });
    }


    public function setCollection(Collection $collection)
    {
        $this->collection = $collection;
        // Recalculate the number of pages.
        $this->model->numberOfPages = $this->calculateNumberOfPages();
        try {
            $this->setPageNumber($this->model->pageNumber);
        } catch (PagerOutOfBoundsException $ex) {
            // If the current page is beyond the new collection, go back to the first page
            $this->setPageNumber(1);
        }
    }

    public function setPageNumber($pageNumber)
    {
        $this->model->pageNumber = $pageNumber;

        if ($this->collection) {
            $this->collectionRangeModified = true;
            $this->collection->setRange((($pageNumber - 1) * $this->model->perPage), $this->model->perPage);
        }
    }

    protected function changePageNumber($pageNumber)
    {
        if ($this->collection) {
            $numberOfPages = $this->calculateNumberOfPages();

            if ($pageNumber > max($numberOfPages, 1)) {
                throw new PagerOutOfBoundsException();
            }

            $this->model->numberOfPages = $numberOfPages;
        }

        $this->setPageNumber($pageNumber);
    }

    protected function parseRequest(WebRequest $request)
    {
        parent::parseRequest($request);

        $key = $this->model->leafPath. "-page";

        if ($request->post($key)) {
            $this->pageChangedEvent->raise($request->post($key));
        }

        if ($request->get($key)) {
            $this->pageChangedEvent->raise($request->get($key));
        }
    }

    public function setNumberPerPage($perPage)
    {
        $this->model->perPage = $perPage;
    }

    protected function beforeRender()
    {
        $this->setPageNumber($this->model->pageNumber);

        if ($this->model->numberOfPages === null){
            $this->model->numberOfPages = $this->calculateNumberOfPages();
        }

        parent::beforeRender();
    }


    /**
     * @return float
     */
    private function calculateNumberOfPages()
    {
        list($collectionSize) = $this->collection->calculateAggregates(new Count("*"));
        $pages = ceil($collectionSize / $this->model->perPage);

        return $pages;
    }

    /**
     * Returns the name of the standard view used for this leaf.
     *
     * @return string
     */
    protected function getViewClass()
    {
        return PagerView::class;
    }

    /**
     * Should return a class that derives from LeafModel
     *
     * @return LeafModel
     */
    protected function createModel()
    {
        return new PagerModel();
    }

    protected function parseUrlState(WebRequest $request)
    {
        if ($this->getUrlStateName()) {
            $pageNumber = (int)$request->get($this->getUrlStateName());
            if ($pageNumber > 1) {
                try {
                    $this->setPageNumber($pageNumber);
                } catch (PagerOutOfBoundsException $ex) {
                    // Ignore if the URL specifies a page too far on for this collection
                    $this->setPageNumber(1);
                }
            }
        }
    }
}
