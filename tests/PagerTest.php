<?php

namespace Gcd\Mvp\Tests\Presenters\ Application\Pager;

use Rhubarb\Crown\Context;
use Rhubarb\Crown\Request\WebRequest;
use Gcd\Mvp\Exceptions\PagerOutOfBoundsException;
use Gcd\Mvp\Presenters\Application\Pager\Pager;
use Gcd\Mvp\Tests\Fixtures\Presenters\UnitTestView;
use Rhubarb\Stem\Collections\Collection;
use Rhubarb\Stem\Tests\Fixtures\ModelUnitTestCase;
use Rhubarb\Stem\Tests\Fixtures\User;

class PagerTest extends ModelUnitTestCase
{
    private $collection;
    /**
     * @var Pager
     */
    private $pager;

    /**
     * @var TestPagerView
     */
    private $mock;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        for ($x = 0; $x < 500; $x++) {
            $user = new User();
            $user->Username = $x;
            $user->save();
        }
    }

    protected function setUp()
    {
        parent::setUp();

        $this->createMocks();
    }

    private function createMocks()
    {
        $this->collection = new Collection(User::class);

        $this->mock = new TestPagerView();

        $this->pager = new Pager($this->collection, 50);
        $this->pager->attachMockView($this->mock);
    }

    public function testPagesCalculatedCorrectly()
    {
        $this->pager->generateResponse();

        $this->assertEquals(10, $this->mock->numberOfPages);
        $this->assertEquals(1, $this->mock->pageNumber);
        $this->assertEquals(50, $this->mock->numberPerPage);

        $this->pager->setNumberPerPage(30);
        $this->pager->generateResponse();

        $this->assertEquals(17, $this->mock->numberOfPages);
        $this->assertEquals(1, $this->mock->pageNumber);
        $this->assertEquals(30, $this->mock->numberPerPage);
    }

    public function testPageNumberCanBeChanged()
    {
        $this->mock->simulateEvent("PageChanged", 2);
        $this->pager->generateResponse();
        $this->assertEquals(2, $this->mock->pageNumber);

        $this->collection->rewind();

        $user = $this->collection->current();

        $this->assertEquals(50, $user->Username);
    }

    public function testPagerStaysInBounds()
    {
        $thrown = false;

        try {
            $this->pager->setPageNumber(11);
        } catch (PagerOutOfBoundsException $er) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertEquals(1, $this->pager->PageNumber);
    }

    public function testPagerPicksUpOnHttpGetPageNumbers()
    {
        $request = new WebRequest();

        $context = new Context();
        $context->Request = $request;

        $request->request($this->pager->PresenterPath . "-page", 3);

        $this->createMocks();

        $this->pager->test();

        $this->assertEquals(3, $this->mock->pageNumber);
    }
}

class TestPagerView extends UnitTestView
{
    public $numberOfPages;
    public $pageNumber;
    public $numberPerPage;

    public function setNumberOfPages($numberOfPages)
    {
        $this->numberOfPages = $numberOfPages;
    }

    public function setPageNumber($pageNumber)
    {
        $this->pageNumber = $pageNumber;
    }

    public function setNumberPerPage($numberPerPage)
    {
        $this->numberPerPage = $numberPerPage;
    }
}
