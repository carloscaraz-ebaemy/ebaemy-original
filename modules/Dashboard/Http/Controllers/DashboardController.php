<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Exports\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Dashboard\Helpers\DashboardData;
use Modules\Dashboard\Helpers\DashboardUtility;
use Modules\Dashboard\Helpers\DashboardSalePurchase;
use Modules\Dashboard\Helpers\DashboardView;
use Modules\Dashboard\Helpers\DashboardStock;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Document;
use App\Models\Tenant\Company;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Arr;
use Modules\Dashboard\Helpers\DashboardInventory;
use Modules\Dashboard\Helpers\DashboardV2;
use App\Models\Tenant\Configuration;

/**
 * Class DashboardController
 *
 * @package Modules\Dashboard\Http\Controllers
 * @mixin Controller
 */
class DashboardController extends Controller
{
    public function index()
    {
        // dd('aqui');
        if(auth()->user()->type != 'admin' && !auth()->user()->searchModule('dashboard')){
            return redirect()->route('tenant.documents.index');
        } elseif (auth()->user()->type == 'admin' && !auth()->user()->searchModule('dashboard')) {
            return redirect()->route('tenant.documents.index');
        }

        $company = Company::select('soap_type_id')->first();
        $soap_company  = $company->soap_type_id;
        $configuration = Configuration::first();

        return view('dashboard::index', compact('soap_company','configuration'));
    }

    public function filter()
    {
        return [
            'establishments' => DashboardView::getEstablishments()
        ];
    }

    public function globalData()
    {
        return response()->json((new DashboardData())->globalData(), 200);
    }

    public function data(Request $request)
    {
        return [
            'data' => (new DashboardData())->data($request->all()),
        ];
    }

    // public function unpaid(Request $request)
    // {
    //     return [
    //             'records' => (new DashboardView())->getUnpaid($request->all())
    //     ];
    // }

    // public function unpaidall()
    // {

    //     return Excel::download(new AccountsReceivable, 'Allclients.xlsx');

    // }

    public function data_aditional(Request $request)
    {
        return [
            'data' => (new DashboardSalePurchase())->data($request->all()),
        ];
    }

    public function stockByProduct(Request $request)
    {
        return  (new DashboardStock())->data($request);
    }


    public function utilities(Request $request)
    {
        return [
            'data' => (new DashboardUtility())->data($request->all()),
        ];
    }

    public function df()
    {
        $path = app_path();
        //df -m -h --output=used,avail,pcent /

        $used = new Process(['df' ,'-m', '-h', '--output=used','/']);
        $used->run();
        if (!$used->isSuccessful()) {
            return ['error'];
            throw new ProcessFailedException($used);
        }
        $disc_used = $used->getOutput();
        $array[] = str_replace("\n","",$disc_used);

        $avail = new Process(['df', '-m', '-h', '--output=avail', '/']);
        $avail->run();
        if (!$avail->isSuccessful()) {
            return ['error'];
            throw new ProcessFailedException($avail);
        }
        $disc_avail = $avail->getOutput();
        $array[] = str_replace("\n","",$disc_avail);

        $pcent = new Process(['df' ,'-m' ,'-h' , '--output=pcent' ,'/']);
        $pcent->run();
        if (!$pcent->isSuccessful()) {
            return ['error'];
            throw new ProcessFailedException($pcent);
        }
        $disc_pcent = $pcent->getOutput();
        $array[] = str_replace("\n","",$disc_pcent);

        return $array;


    }

    /**
     * Extensión de ventas por producto
     *
     */
    public function salesByProduct()
    {
        return view('dashboard::sales_by_product');
    }

    public function productOfDue(Request $request)
    {
        return  (new DashboardInventory())->data($request);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  V2 ENDPOINTS — Dashboard operativo
    // ─────────────────────────────────────────────────────────────────────

    private function v2(Request $request): DashboardV2
    {
        return new DashboardV2($request->input('establishment_id') ? (int)$request->input('establishment_id') : null);
    }

    public function v2Summary(Request $request)
    {
        return response()->json($this->v2($request)->summary());
    }

    public function v2DailyChart(Request $request)
    {
        return response()->json($this->v2($request)->salesDailyChart());
    }

    public function v2MonthlyChart(Request $request)
    {
        return response()->json($this->v2($request)->salesMonthlyChart());
    }

    public function v2TopSellers(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());

        return response()->json($this->v2($request)->topSellers($start, $end));
    }

    public function v2TopProducts(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());

        return response()->json($this->v2($request)->topProducts($start, $end));
    }

    public function v2StockAlerts(Request $request)
    {
        return response()->json($this->v2($request)->stockAlerts());
    }

    public function v2Purchases(Request $request)
    {
        return response()->json($this->v2($request)->purchaseSummary());
    }

    public function v2Alerts(Request $request)
    {
        return response()->json($this->v2($request)->alerts());
    }

    public function v2Receivables(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->receivables($start, $end));
    }

    public function v2Customers(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->customers($start, $end));
    }

    public function v2PaymentMethods(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->paymentMethods($start, $end));
    }

    public function v2Profitability(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->profitability($start, $end));
    }

    public function v2PeriodComparison(Request $request)
    {
        return response()->json($this->v2($request)->periodComparison());
    }

    public function v2InventoryAdvanced(Request $request)
    {
        return response()->json($this->v2($request)->inventoryAdvanced());
    }

    public function v2SalesByHour(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->salesByHour($start, $end));
    }

    public function v2QuotationConversion(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->quotationConversion($start, $end));
    }

    public function v2SalesByCity(Request $request)
    {
        $start = $request->input('date_start', now()->startOfMonth()->toDateString());
        $end   = $request->input('date_end',   now()->endOfMonth()->toDateString());
        return response()->json($this->v2($request)->salesByCity($start, $end));
    }

}
