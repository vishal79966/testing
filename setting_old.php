<?php

namespace App\Http\Controllers\Admin;
/**
 * Settings Controller
 *
 * @package TokenLite
 * @author Softnio
 * @version 1.1.0
 */
use Validator;
use IcoHandler;
use App\Models\Setting;
use Carbon\Carbon;
use App\Helpers\TokenCalculate as TC;
use App\Models\IcoStage;
use App\Models\Transaction;
use App\Models\LotterySettings;
use App\Models\GlobalMeta;
use Illuminate\Http\Request;
use App\Helpers\ReferralHelper;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Tickets;
use DB;

class SettingController extends Controller
{

    /**
     * Display the settings page
     *
     * @return \Illuminate\Http\Response
     * @version 1.0.0
     * @since 1.0
     * @return void
     */
    public function index() {
        $timezones = IcoHandler::get_timezones();
        return view('admin.settings', compact('timezones'));
    }

    /**
     * Display the API settings page
     *
     * @return \Illuminate\Http\Response
     * @version 1.0.0
     * @since 1.0.6
     * @return void
     */
    public function api_setting() {
        return view('admin.restapi');
    }


    /**
     * Display the Referral page
     *
     * @return \Illuminate\Http\Response
     * @version 1.0.0
     * @since 1.0.6
     * @return void
     */
    public function referral_setting() {
        $general = ReferralHelper::general_option();
        $advanced = ReferralHelper::advanced_option();
        return view('admin.settings-referral', compact('general', 'advanced'));
    }


    /**
     * Update the settings Data
     *
     * @return \Illuminate\Http\Response
     * @version 1.0.2
     * @since 1.0
     * @return void
     */
    public function update(Request $request) {
        $type = $request->input('type');
        $ret['msg'] = 'info';
        $ret['message'] = __('messages.nothing');

        if ($type == 'api_settings') {
            $setting = Setting::updateValue('site_api_key', str_random(24));
            $ret['msg'] = 'success';
            $ret['message'] = __('messages.update.success', ['what' => 'New API Key']);
        }

        if ($type == 'site_info') {
            $validator = Validator::make($request->all(), [
                'site_name' => 'required|min:4',
                'site_email' => 'required|email'
            ]);

            if ($validator->fails()) {
                $msg = '';
                if ($validator->errors()->hasAny(['site_name', 'site_email'])) {
                    $msg = $validator->errors()->first();
                } else {
                    $msg = __('messages.form.wrong');
                }

                $ret['msg'] = 'warning';
                $ret['message'] = $msg;
                return response()->json($ret);
            } else {
                $ret['msg'] = 'warning';
                $ret['message'] = __('messages.update.failed', ['what' => 'Settings']);
                Setting::updateValue(Setting::SITE_NAME, $request->input('site_name'));
                Setting::updateValue(Setting::SITE_EMAIL, $request->input('site_email'));
                Setting::updateValue('site_copyright', $request->input('site_copyright'));
                Setting::updateValue('site_support_address', $request->input('site_support_address'));
                Setting::updateValue('site_support_phone', $request->input('site_support_phone'));
                Setting::updateValue('site_support_email', $request->input('site_support_email'));
                Setting::updateValue('main_website_url', $request->input('main_website_url'));


                $ret['msg'] = 'success';
                $ret['message'] = __('messages.update.success', ['what' => 'Settings']);
            }
        }elseif ($type == 'social_links') {
            $ret['msg'] = 'warning';
            $ret['message'] = __('messages.update.failed', ['what' => 'Social Links']);

            $links = json_encode($request->input('social'));
            Setting::updateValue('site_social_links', $links);
            $ret['msg'] = 'success';
            $ret['message'] = __('messages.update.success', ['what' => 'Social Links']);
        }elseif ($type == 'general') {
            $ret['msg'] = 'warning';
            $ret['message'] = __('messages.update.failed', ['what' => 'General Settings']);

            Setting::updateValue('site_maintenance', (isset($request->site_maintenance) ? 1 : 0));
            Setting::updateValue('site_maintenance_text', $request->input('site_maintenance_text'));
            Setting::updateValue('site_date_format', $request->input('site_date_format'));
            Setting::updateValue('site_time_format', $request->input('site_time_format'));
            Setting::updateValue('site_timezone', $request->input('site_timezone'));
            Setting::updateValue('theme_custom', isset($request->theme_custom));
            if ($request->input('theme_user') || $request->input('theme_admin')) {
                Setting::updateValue('theme_user', $request->input('theme_user'));
                Setting::updateValue('theme_admin', $request->input('theme_admin'));
                \Artisan::call('config:clear');
            }
            Setting::updateValue('theme_auth_layout', $request->input('theme_auth_layout'));

            $ret['msg'] = 'success';
            $ret['message'] = __('messages.update.success', ['what' => 'General Settings']);
        }elseif ($type == 'api_credetial') {
            $ret['msg'] = 'warning';
            $ret['message'] = __('messages.update.failed', ['what' => 'API Credentials']);

            Setting::updateValue('site_api_fb_id', $request->input('api_fb_id'));
            Setting::updateValue('site_api_fb_secret', $request->input('api_fb_secret'));
            Setting::updateValue('site_api_google_id', $request->input('api_google_id'));
            Setting::updateValue('site_api_google_secret', $request->input('api_google_secret'));
            Setting::updateValue('recaptcha_site_key', $request->input('recaptcha_site_key'));
            Setting::updateValue('recaptcha_secret_key', $request->input('recaptcha_secret_key'));

            $ret['msg'] = 'success';
            $ret['message'] = __('messages.update.success', ['what' => 'API Credentials']);
        }elseif ($type == 'custom_code') {
            $ret['msg'] = 'warning';
            $ret['message'] = __('messages.update.failed', ['what' => 'Custom Code ']);

            Setting::updateValue('site_header_code', $request->input('site_header_code'));
            Setting::updateValue('site_footer_code', $request->input('site_footer_code'));

            $ret['msg'] = 'success';
            $ret['message'] = __('messages.update.success', ['what' => 'Header & Footer Custom Code']);
        }elseif($type == 'referral') {
            $validator = Validator::make($request->all(), [
                'referral_bonus' => 'integer|gte:0',
                'referral_bonus_join' => 'integer|gte:0'
            ]);

            if ($validator->fails()) {
                $msg = '';
                if ($validator->errors()->hasAny(['referral_bonus', 'referral_bonus_join'])) {
                    $msg = $validator->errors()->first();
                } else {
                    $msg = __('messages.update.failed', ['what' => 'Referral Settings']);
                }

                $ret['msg'] = 'warning';
                $ret['message'] = $msg;
            } else {
                if(nio_feature() && !empty($request->input('referral_extend_bonus'))) {
                    $extend_bonus = json_encode($request->input('referral_extend_bonus'));
                    Setting::updateValue('referral_extend_bonus', $extend_bonus);
                }
                Setting::updateValue('referral_system', (isset($request->referral_system) ? 1 : 0));
                Setting::updateValue('referral_allow', $request->input('referral_allow'));
                Setting::updateValue('referral_calc', $request->input('referral_calc'));
                Setting::updateValue('referral_bonus', $request->input('referral_bonus'));
                Setting::updateValue('referral_allow_join', $request->input('referral_allow_join'));
                Setting::updateValue('referral_calc_join', $request->input('referral_calc_join'));
                Setting::updateValue('referral_bonus_join', $request->input('referral_bonus_join'));
                Setting::updateValue('referral_info_show', (isset($request->referral_info_show) ? 1 : 0));

                $ret['msg'] = 'success';
                $ret['message'] = __('messages.update.success', ['what' => 'Referral Settings']);
            }
        }



        if ($request->ajax()) {
            return response()->json($ret);
        }
        return back()->with([$ret['msg'] => $ret['message']]);
    }

    /**
     * Update meta data for settings
     *
     * @return \Illuminate\Http\Response
     * @version 1.0
     * @since 1.1.0
     * @return void
     */
    public function update_meta(Request $request)
    {
        $type = $request->input('type');
        $ret['msg'] = 'info';
        $ret['message'] = __('messages.nothing');
        $auth_id = auth()->id();
        $is_saved = false;

        $type_key = 'default'; $is_page_meta = false;
        if($type == 'tnx_page_meta' || $type == 'kyc_page_meta' || $type == 'user_page_meta') {
            $type_key = str_replace('_page_meta', '', $type);
            $is_page_meta = true;
        }

        if ($is_page_meta==true) {
            $meta_name = $this->meta_key_val($request->meta, 'key');
            $meta_val = $this->meta_key_val($request->meta, 'value');
            $ret['msg'] = 'error';
            $ret['message'] = __('messages.update.failed', ['what' => 'Options']);

            if($meta_name=='perpage') {
                $meta_by_name = $type_key.'_per_page';
                $result = GlobalMeta::save_meta($meta_by_name, $meta_val, $auth_id);
                $is_saved = true;
            } elseif($meta_name=='ordered') {
                $meta_by_name = $type_key.'_ordered';
                $result = GlobalMeta::save_meta($meta_by_name, $meta_val, $auth_id);
                $is_saved = true;
            } elseif($meta_name=='orderby') {
                $meta_by_name = $type_key.'_order_by';
                $result = GlobalMeta::save_meta($meta_by_name, $meta_val, $auth_id);
                $is_saved = true;
            } else {
                $meta_by_name = $type_key.'_'.$meta_name;
                $result = GlobalMeta::save_meta($meta_by_name, $meta_val, $auth_id);
                $is_saved = true;
            }
            if($is_saved) {
                $ret['msg'] = 'success';
                $ret['message'] = __('messages.update.success', ['what' => 'Options']);
            }
        }

        if ($request->ajax()) {
            return response()->json($ret);
        }
        return back()->with([$ret['msg'] => $ret['message']]);
    }

    private function meta_key_val($value, $output=null)
    {
        $value = explode('=', $value);
        $return = array('key' => $value[0], 'value'=> $value[1]);
        return (empty($output)) ? $return : (isset($return[$output]) ? $return[$output] : '');
    }

    public function lotterySettings()
    {
        $pm_gateways = PaymentMethod::Currency;
        $lottery_details = LotterySettings::first();
        return view('admin.lotterySettings',compact('pm_gateways','lottery_details'));
    }

    public function lotterySettingsStore()
    {
        $newtick = 100;
        // $answer = abs($num1 - $num2);
            $tickets = DB::table('lottery_stat')->where('status',0)->first();
            $l_round = $tickets->lottery_round;
                $ans = (($tickets->ticket_sold)+$newtick);
                if ($ans > 500) {
                   $n_tick = ($newtick - ($tickets->pending_ticket_sold));
                   // $r_tick = (($tickets->ticket_sold)-$n_tick);
                   $c_tick = (($tickets->ticket_sold) + ($tickets->pending_ticket_sold));
                   $p_tick = (($tickets->pending_ticket_sold)-($tickets->pending_ticket_sold));



                    $tickets_store = DB::table('lottery_stat')->where('lottery_round',$l_round)->update(['ticket_sold'=>$c_tick,'pending_ticket_sold'=>$p_tick,'status'=>2]);

                    $n_tickets = DB::table('lottery_stat')->where('status',0)->first();
                    $nl_round = $n_tickets->lottery_round;
                    $np_tick = (($n_tickets->pending_ticket_sold)- $n_tick);

                    $ntickets_store = DB::table('lottery_stat')->where('lottery_round',$nl_round)->update(['ticket_sold'=>$n_tick,'pending_ticket_sold'=>$np_tick]);

                    //Adding Manupilated Tickets
                    $tc = new TC();
                    $all_currency_rate = json_encode(Setting::exchange_rate($tc->get_current_price(), 'except'));
                    $base_amount = 180;

                    $save_data = [
                'created_at' => date('Y-m-d H:i:s'),
                'tnx_id' => set_id(rand(100, 999), 'trnx'),
                'tnx_type' => 'purchase',
                'tnx_time' => date('Y-m-d H:i:s'),
                'tokens' => 90,
                'bonus_on_base' => 0,
                'bonus_on_token' => 0,
                'total_bonus' => 0,
                'total_tokens' => 900,
                'stage' => 1,
                'user' => 62,
                'amount' => 180,
                'receive_amount' => 180,
                'receive_currency' => 'usd',
                'base_amount' => $base_amount,
                'base_currency' => 'usd',
                'base_currency_rate' => 2,
                'currency' => 'usd',
                'currency_rate' => 2,
                'all_currency_rate' => $all_currency_rate,
                'payment_method' => 'manual',
                'payment_to' => '',
                'payment_id' => rand(1000, 9999),
                'details' => 'Token Purchase',
                'status' => 'onhold',
            ];

            $iid = Transaction::insertGetId($save_data);

            if ($iid != null) {

                $address = '';
                $transaction = Transaction::where('id', $iid)->first();
                $transaction->tnx_id = set_id($iid, 'trnx');
                $transaction->wallet_address = '';
                $transaction->extra = null;
                $transaction->status = 'approved';
                $transaction->save();

                IcoStage::token_add_to_account($transaction, 'add');

                // $transaction->checked_by = json_encode(['name' => Auth::user()->name, 'id' => Auth::id()]);

                // $transaction->added_by = set_added_by(Auth::id(), Auth::user()->role);
                // $transaction->checked_time = now();
                // $transaction->save();
                // // Start adding
                // IcoStage::token_add_to_account($transaction, '', 'add');
            }
               

                $trnx = Transaction::where('id', $iid)->first();
                $tick = (180/10);

                        for ($x = 1; $x <= $tick; $x++) {

                        $lottery_tickets_add = DB::table('lottery_tickets')->insert([
                            'tckt_tnx_id' =>set_id(rand(100, 999), 'ticket'),
                            'tckt_tnx_time' => date('Y-m-d H:i:s'),
                            'purchase_tnx_id'=>$trnx->tnx_id,
                            'purchase_amount' => 180,
                            'total_purchase_amount'=>180,
                            'token' => $trnx->tokens,
                            'total_token'=> $trnx->tokens,
                            'total_bonus' =>0,
                            'added_by'=>'SYSTEM',
                            'user'=>  $trnx->user,

                    ]);

                    }

                    $m_tickets = DB::table('lottery_stat')->where('status',0)->first();
                    $nl_round = $m_tickets->lottery_round;
                    $mc_tick = (($m_tickets->ticket_sold) + 18);
                    $mp_tick = (($m_tickets->pending_ticket_sold) - 18);

                    $ntickets_store = DB::table('lottery_stat')->where('lottery_round',$nl_round)->update(['ticket_sold'=>$mc_tick,'pending_ticket_sold'=>$mp_tick]);

                }elseif($ans<500) {

                        $t_tick = (($tickets->ticket_sold)+$newtick);
                        $p_tick = (($tickets->pending_ticket_sold)-$newtick);

                    $tickets_store = DB::table('lottery_stat')->where('lottery_round',$l_round)->update(['ticket_sold'=>$t_tick,'pending_ticket_sold'=>$p_tick]);
                }
        dd('welcome');

        $insert = DB::table('lottery_settings')->where('id', 1)->update([
            'total_round' => request()->total_round,
            'round_interval' => request()->round_interval,
            'ticket_price' => request()->ticket_price,
            'lucky_number_slot' => request()->lucky_number_slot,
            'percentage_winning_1' => request()->percentage_winning_1,
            'percentage_winning_2' => request()->percentage_winning_2,
            'percentage_winning_3' => request()->percentage_winning_3,
            'percentage_winning_4' => request()->percentage_winning_4,
            'percentage_winning_5' => request()->percentage_winning_5,
            'refund_allowed' => request()->refund_allowed,
            'refund_currency' => request()->refund_currency,
        ]);
        // $insert = DB::table('lottery_settings')->insertGetId([
        //     'total_round' => request()->total_round,
        //     'round_interval' => request()->round_interval,
        //     'ticket_price' => request()->ticket_price,
        //     'lucky_number_slot' => request()->lucky_number_slot,
        //     'percentage_winning_1' => request()->percentage_winning_1,
        //     'percentage_winning_2' => request()->percentage_winning_2,
        //     'percentage_winning_3' => request()->percentage_winning_3,
        //     'percentage_winning_4' => request()->percentage_winning_4,
        //     'percentage_winning_5' => request()->percentage_winning_5,
        //     'refund_allowed' => request()->refund_allowed,
        //     'refund_currency' => request()->refund_currency,
        // ]);
        $data = DB::table('lottery_settings')
                ->where('id',1)
                ->first();
        $totalRound = $data->total_round;
        // dd($totalRound);
        for ($x = 1; $x <= $totalRound; $x++) {

        $l_stat = DB::table('lottery_stat')->insert([
                'lottery_round' => $x,
                'pending_ticket_sold'=>500,
                'status'=>0,
        ]);
            }

        if ($l_stat) {
             $ret['msg'] = 'success';
            $ret['message'] = __('messages.update.success', ['what' => 'Options']);
        }
        return back()->with([$ret['msg'] => $ret['message']]);
    }
}
