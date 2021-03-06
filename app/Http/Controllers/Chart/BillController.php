<?php
/**
 * BillController.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Http\Controllers\Chart;

use Carbon\Carbon;
use FireflyIII\Generator\Chart\Bill\BillChartGeneratorInterface;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Models\Bill;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\Support\CacheProperties;
use Response;

/**
 * Class BillController
 *
 * @package FireflyIII\Http\Controllers\Chart
 */
class BillController extends Controller
{

    /** @var  \FireflyIII\Generator\Chart\Bill\BillChartGeneratorInterface */
    protected $generator;

    /**
     * checked
     */
    public function __construct()
    {
        parent::__construct();
        // create chart generator:
        $this->generator = app(BillChartGeneratorInterface::class);
    }

    /**
     * Shows all bills and whether or not they've been paid this month (pie chart).
     *
     * @param BillRepositoryInterface $repository
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function frontpage(BillRepositoryInterface $repository)
    {
        $start  = session('start', Carbon::now()->startOfMonth());
        $end    = session('end', Carbon::now()->endOfMonth());
        $paid   = $repository->getBillsPaidInRange($start, $end); // will be a negative amount.
        $unpaid = $repository->getBillsUnpaidInRange($start, $end); // will be a positive amount.
        $data   = $this->generator->frontpage($paid, $unpaid);

        return Response::json($data);
    }

    /**
     * Shows the overview for a bill. The min/max amount and matched journals.
     *
     * @param BillRepositoryInterface $repository
     * @param Bill                    $bill
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function single(BillRepositoryInterface $repository, Bill $bill)
    {
        $cache = new CacheProperties;
        $cache->addProperty('single');
        $cache->addProperty('bill');
        $cache->addProperty($bill->id);
        if ($cache->has()) {
            return Response::json($cache->get());
        }

        // get first transaction or today for start:
        $results = $repository->getJournals($bill, 1, 200);

        // resort:
        $results = $results->sortBy(
            function (TransactionJournal $journal) {
                return $journal->date->format('U');
            }
        );

        $data = $this->generator->single($bill, $results);
        $cache->store($data);

        return Response::json($data);
    }
}
