<?php
session_start();

/* ================= CONFIGURATION ================= */
$ADMIN_USER = "admin";
$ADMIN_PASS = "admin123";

$CITIES = ['Mumbai','Delhi','Bangalore','Ahmedabad','Pune'];

// Product Database
$PRODUCTS_DB = [
    '99012345'=>['id'=>'99012345','barcode'=>'99012345','name'=>'Slim Shirt','price'=>2999,'cost'=>1200,'discount'=>10,'tax'=>18, 'category'=>'Apparel', 'image'=>'👕'],
    '99012346'=>['id'=>'99012346','barcode'=>'99012346','name'=>'Denim Jeans','price'=>3499,'cost'=>1500,'discount'=>0,'tax'=>12, 'category'=>'Apparel', 'image'=>'👖'],
    '99012347'=>['id'=>'99012347','barcode'=>'99012347','name'=>'Sugar 1kg','price'=>50,'cost'=>42,'discount'=>5,'tax'=>0, 'category'=>'Grocery', 'image'=>'🍬'],
    '99012348'=>['id'=>'99012348','barcode'=>'99012348','name'=>'Almond Milk','price'=>250,'cost'=>180,'discount'=>15,'tax'=>5, 'category'=>'Grocery', 'image'=>'🥛'],
    '99012349'=>['id'=>'99012349','barcode'=>'99012349','name'=>'Running Shoes','price'=>4999,'cost'=>2000,'discount'=>20,'tax'=>18, 'category'=>'Footwear', 'image'=>'👟'],
    '99012350'=>['id'=>'99012350','barcode'=>'99012350','name'=>'Smart Watch','price'=>15000,'cost'=>8000,'discount'=>5,'tax'=>18, 'category'=>'Electronics', 'image'=>'⌚'],
];

/* ================= HELPER FUNCTIONS ================= */
function money($v){ return '₹'.number_format($v,0); }

function calculate_totals($cart){
    $t=['final'=>0, 'subtotal'=>0, 'tax'=>0, 'savings'=>0, 'total_cost'=>0];
    foreach($cart as $i){
        $mrp = $i['price'] * $i['qty'];
        $discount_amt = ($i['price'] * $i['discount'] / 100) * $i['qty'];
        $taxable_val = $mrp - $discount_amt;
        $tax_amt = $taxable_val * ($i['tax'] / 100);
        $cost_amt = $i['cost'] * $i['qty'];
        
        $t['subtotal'] += $mrp;
        $t['savings'] += $discount_amt;
        $t['tax'] += $tax_amt;
        $t['total_cost'] += $cost_amt;
        $t['final'] += ($taxable_val + $tax_amt);
    }
    $t['profit'] = ($t['final'] - $t['tax']) - $t['total_cost'];
    return $t;
}

/* ================= SESSION INITIALIZATION ================= */
$_SESSION['view'] = $_SESSION['view'] ?? 'auth';
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['tx'] = $_SESSION['tx'] ?? [];
$_SESSION['tab'] = $_SESSION['tab'] ?? 'scan';
$_SESSION['city'] = $_SESSION['city'] ?? '';
$_SESSION['customer'] = $_SESSION['customer'] ?? ['name'=>'','phone'=>''];
$_SESSION['current_bill_id'] = $_SESSION['current_bill_id'] ?? null;
$_SESSION['admin_filter_city'] = $_SESSION['admin_filter_city'] ?? 'All';

/* ================= CONTROLLER LOGIC ================= */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';

    // --- Authentication ---
    if($action === 'customer_login'){
        $_SESSION['view']='customer';
        $_SESSION['city']=$_POST['city'];
        $_SESSION['customer']['name'] = htmlspecialchars($_POST['cust_name']);
        $_SESSION['customer']['phone'] = htmlspecialchars($_POST['cust_phone']);
        $_SESSION['cart']=[]; 
        $_SESSION['tab']='scan';
    }
    elseif($action === 'admin_login'){
        if($_POST['user']===$ADMIN_USER && $_POST['pass']===$ADMIN_PASS){
            $_SESSION['view']='admin';
        } else {
            $_SESSION['error']="Invalid credentials";
        }
    }
    elseif($action === 'logout'){
        session_destroy();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    elseif($action === 'switch_to_pos'){
        $_SESSION['view'] = 'customer';
        $_SESSION['tab'] = 'scan';
    }
    elseif($action === 'back_to_home'){
        $_SESSION['view'] = 'customer';
        $_SESSION['tab'] = 'scan';
    }
    elseif($action === 'admin_back'){
        $_SESSION['view'] = 'admin';
    }
    
    // --- Admin Actions ---
    elseif($action === 'admin_filter'){
        $_SESSION['admin_filter_city'] = $_POST['filter_city'];
    }

    // --- Navigation ---
    elseif($action === 'tab'){ 
        $_SESSION['tab'] = $_POST['tab']; 
    }

    // --- Scanning & Cart ---
    elseif($action === 'scan'){
        $code = trim($_POST['barcode']);
        if(empty($code)) {
            $_SESSION['toast'] = "Please enter a code";
        }
        elseif(isset($PRODUCTS_DB[$code])){
            if(!isset($_SESSION['cart'][$code])) {
                $_SESSION['cart'][$code] = $PRODUCTS_DB[$code];
                $_SESSION['cart'][$code]['qty'] = 0;
            }
            $_SESSION['cart'][$code]['qty']++;
            $_SESSION['toast'] = "Added " . $PRODUCTS_DB[$code]['name'];
        } else {
            $_SESSION['toast'] = "Product not found";
        }
    }
    elseif($action === 'remove'){ 
        unset($_SESSION['cart'][$_POST['id']]); 
    }
    elseif($action === 'update_qty'){
        $id = $_POST['id'];
        $qty = (int)$_POST['qty'];
        if($qty <= 0) unset($_SESSION['cart'][$id]);
        else $_SESSION['cart'][$id]['qty'] = $qty;
    }

    // --- Checkout & Bill Generation ---
    elseif($action === 'checkout'){
        if(!empty($_SESSION['cart'])) {
            $totals = calculate_totals($_SESSION['cart']);
            $tx_id = strtoupper(uniqid('BILL-'));
            $new_tx = [
                'id' => $tx_id,
                'city' => $_SESSION['city'],
                'customer_name' => $_SESSION['customer']['name'],
                'customer_phone' => $_SESSION['customer']['phone'],
                'payment_method' => $_POST['payment_method'],
                'items_count' => count($_SESSION['cart']),
                'cart_details' => $_SESSION['cart'],
                'totals' => $totals,
                'date' => date('Y-m-d H:i:s')
            ];
            
            $_SESSION['tx'][] = $new_tx;
            $_SESSION['current_bill_id'] = $tx_id; // Set for bill view
            $_SESSION['cart']=[]; 
            $_SESSION['view']='bill'; // Redirect to bill view
        }
    }
    elseif($action === 'view_bill'){
        $_SESSION['current_bill_id'] = $_POST['tx_id'];
        $_SESSION['view'] = 'bill';
    }

    header("Location: ".$_SERVER['PHP_SELF']); 
    exit;
}

$view = $_SESSION['view']; 
$tab = $_SESSION['tab'];
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <title>Luminous POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Scripts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> <!-- Added AlpineJS for Tabs -->
    <script src="https://unpkg.com/quagga@0.12.1/dist/quagga.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // --- Theme Management ---
        function applyTheme(theme) {
            const root = document.getElementById('html-root');
            if(theme === 'dark') {
                root.classList.add('dark');
                root.classList.remove('light');
                document.documentElement.style.setProperty('--bg-body', '#000000');
                document.documentElement.style.setProperty('--bg-panel', '#121212');
                document.documentElement.style.setProperty('--text-main', '#ffffff');
                document.documentElement.style.setProperty('--text-muted', '#a0aec0');
                document.documentElement.style.setProperty('--border-color', '#2d3748');
            } else {
                root.classList.add('light');
                root.classList.remove('dark');
                document.documentElement.style.setProperty('--bg-body', '#f3f4f6'); // gray-100
                document.documentElement.style.setProperty('--bg-panel', '#ffffff');
                document.documentElement.style.setProperty('--text-main', '#111827'); // gray-900
                document.documentElement.style.setProperty('--text-muted', '#6b7280'); // gray-500
                document.documentElement.style.setProperty('--border-color', '#e5e7eb'); // gray-200
            }
            localStorage.setItem('pos_theme', theme);
        }

        function setTheme(theme) {
            applyTheme(theme);
        }

        // Init Theme
        window.addEventListener('DOMContentLoaded', () => {
            const saved = localStorage.getItem('pos_theme') || 'light'; 
            applyTheme(saved);
        });
    </script>
    <style>
        :root {
            --primary: #2563eb; 
            --primary-hover: #1d4ed8;
            --bg-body: #f3f4f6;
            --bg-panel: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            transition: background-color 0.3s, color 0.3s;
        }

        .panel {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-color);
        }

        .input-field {
            background-color: var(--bg-body);
            border: 1px solid var(--border-color);
            color: var(--text-main);
        }

        /* Scanner */
        #scanner video { width: 100%; height: 100%; object-fit: cover; border-radius: 1rem; }
        #scanner canvas { display: none; }
        
        /* Print Styles */
        @media print {
            body * { visibility: hidden; }
            #printable-bill, #printable-bill * { visibility: visible; }
            #printable-bill { position: absolute; left: 0; top: 0; width: 100%; background: white; color: black; padding: 20px; box-shadow: none; border: none; }
            .no-print { display: none !important; }
        }

        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col transition-colors duration-300">

<!-- GLOBAL TOAST -->
<?php if(isset($_SESSION['toast'])): ?>
    <div x-data="{show: true}" x-init="setTimeout(() => show = false, 3000)" x-show="show" 
         class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 bg-[#2563eb] text-white px-6 py-3 rounded-full shadow-xl flex items-center gap-2 fade-in font-medium">
        <span>✓</span> <?= $_SESSION['toast']; unset($_SESSION['toast']); ?>
    </div>
<?php endif; ?>

<!-- ==================== VIEW: AUTH ==================== -->
<?php if($view === 'auth'): ?>
<div class="flex-1 flex flex-col items-center justify-center p-6">
    <div class="w-full max-w-sm fade-in panel p-8 rounded-2xl shadow-xl">
        <!-- Logo Area -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl shadow-lg shadow-blue-500/30 mb-4 transform -rotate-3">
                <span class="text-3xl text-white">⚡</span>
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-[var(--text-main)]">Luminous POS</h1>
            <p class="text-[var(--text-muted)] text-sm mt-2">Enterprise Billing System</p>
        </div>

        <div x-data="{ tab: 'customer' }" class="space-y-6">
            <!-- Tabs -->
            <div class="flex border-b border-[var(--border-color)] mb-6">
                <button @click="tab = 'customer'" :class="tab==='customer' ? 'border-blue-500 text-blue-600' : 'border-transparent text-[var(--text-muted)]'" class="flex-1 pb-3 text-sm font-semibold border-b-2 transition-colors">Staff Login</button>
                <button @click="tab = 'admin'" :class="tab==='admin' ? 'border-blue-500 text-blue-600' : 'border-transparent text-[var(--text-muted)]'" class="flex-1 pb-3 text-sm font-semibold border-b-2 transition-colors">Admin Portal</button>
            </div>

            <!-- Customer Form -->
            <form x-show="tab === 'customer'" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="customer_login">
                <div>
                    <label class="text-xs font-bold uppercase text-[var(--text-muted)] mb-1 block">Staff Name</label>
                    <input required name="cust_name" class="w-full input-field rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold uppercase text-[var(--text-muted)] mb-1 block">Terminal ID</label>
                    <input required name="cust_phone" class="w-full input-field rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold uppercase text-[var(--text-muted)] mb-1 block">Store Location</label>
                    <select name="city" class="w-full input-field rounded-lg px-4 py-3 text-sm outline-none appearance-none">
                        <?php foreach($CITIES as $c): ?>
                            <option value="<?= $c ?>"><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-500/20 transition-all mt-2">Start Session</button>
            </form>

            <!-- Admin Form (Logic Fixed with AlpineJS) -->
            <form x-show="tab === 'admin'" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="admin_login">
                <div>
                    <label class="text-xs font-bold uppercase text-[var(--text-muted)] mb-1 block">Username</label>
                    <input name="user" class="w-full input-field rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="text-xs font-bold uppercase text-[var(--text-muted)] mb-1 block">Password</label>
                    <input type="password" name="pass" class="w-full input-field rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button class="w-full bg-[var(--text-main)] text-[var(--bg-panel)] hover:opacity-90 font-bold py-3.5 rounded-xl transition-all mt-2">Access Dashboard</button>
            </form>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-500 text-center text-xs font-medium"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ==================== VIEW: CUSTOMER APP ==================== -->
<?php elseif($view === 'customer'): ?>
<div class="flex flex-col h-screen">
    
    <!-- Top Bar -->
    <div class="h-16 border-b border-[var(--border-color)] flex items-center justify-between px-6 panel z-10 sticky top-0 shadow-sm">
        <div class="font-bold text-xl tracking-tight text-[var(--text-main)]">
            <span class="text-blue-600">Quick</span>Bill
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <div class="text-sm font-semibold text-[var(--text-main)]"><?= explode(' ',$_SESSION['customer']['name'])[0] ?></div>
                <div class="text-xs text-[var(--text-muted)]">📍 <?= $_SESSION['city'] ?></div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="logout">
                <button class="bg-red-50/50 hover:bg-red-100 text-red-500 p-2 rounded-lg transition-colors border border-red-200"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></button>
            </form>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto relative bg-[var(--bg-body)]">
        
        <?php if($tab === 'scan'): ?>
        <!-- TAB: SCANNER -->
        <div class="h-full flex flex-col p-4 max-w-2xl mx-auto w-full">
            <div class="flex-1 bg-black rounded-3xl relative overflow-hidden flex items-center justify-center shadow-2xl mb-4 border border-[var(--border-color)]">
                <div id="scanner" class="absolute inset-0"></div>
                <div class="absolute inset-0 border-2 border-blue-500/50 pointer-events-none m-8 rounded-2xl z-10">
                    <div class="absolute top-1/2 w-full h-0.5 bg-red-500/80 animate-pulse shadow-[0_0_10px_rgba(239,68,68,0.5)]"></div>
                </div>
                <div class="z-20 text-center pointer-events-none bg-black/50 px-4 py-2 rounded-full backdrop-blur-sm">
                    <p class="text-white text-sm font-medium">Scanning Mode Active</p>
                </div>
            </div>
            
            <!-- Manual Entry -->
            <div class="panel p-4 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <form method="POST" id="manualForm" class="flex gap-3 mb-4">
                    <input type="hidden" name="action" value="scan">
                    <input type="text" name="barcode" id="manualInput" placeholder="Type barcode..." 
                           class="flex-1 input-field rounded-xl px-5 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500 outline-none">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl w-14 flex items-center justify-center font-bold text-xl shadow-lg transition-all">+</button>
                </form>
                <!-- Quick Items -->
                <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
                    <?php foreach($PRODUCTS_DB as $p): ?>
                        <button onclick="document.getElementById('manualInput').value='<?= $p['barcode'] ?>'; document.getElementById('manualForm').submit();" 
                                class="flex-shrink-0 panel hover:bg-[var(--bg-body)] border border-[var(--border-color)] rounded-xl px-4 py-3 flex items-center gap-3 transition-colors min-w-[160px]">
                            <span class="text-2xl"><?= $p['image'] ?></span>
                            <div class="text-left">
                                <div class="text-xs font-bold text-[var(--text-main)]"><?= $p['name'] ?></div>
                                <div class="text-[10px] text-[var(--text-muted)]"><?= money($p['price']) ?></div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
            if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                Quagga.init({
                    inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#scanner') },
                    decoder: { readers: ["code_128_reader", "ean_reader"] }
                }, function(err) {
                    if (!err) Quagga.start();
                });
                Quagga.onDetected(function(data) {
                    document.getElementById('manualInput').value = data.codeResult.code;
                    document.getElementById('manualForm').submit();
                });
            }
        </script>

        <?php elseif($tab === 'cart'): ?>
        <!-- TAB: CART -->
        <div class="p-4 space-y-4 pb-24 max-w-2xl mx-auto">
            <?php if(empty($_SESSION['cart'])): ?>
                <div class="flex flex-col items-center justify-center pt-32 text-[var(--text-muted)]">
                    <div class="w-20 h-20 rounded-full bg-[var(--bg-panel)] border border-[var(--border-color)] flex items-center justify-center mb-4 text-3xl shadow-sm">🛒</div>
                    <p class="font-medium">Your cart is empty</p>
                    <button onclick="document.querySelector('[name=tab][value=scan]').parentElement.submit()" class="mt-4 text-blue-600 text-sm font-bold hover:underline">Start Scanning</button>
                </div>
            <?php else: ?>
                <!-- Cart List -->
                <div class="space-y-3">
                    <?php foreach($_SESSION['cart'] as $id => $item): ?>
                    <div class="panel p-4 rounded-xl shadow-sm border border-[var(--border-color)] flex items-center gap-4">
                        <div class="w-12 h-12 bg-[var(--bg-body)] rounded-lg flex items-center justify-center text-xl"><?= $item['image'] ?></div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-[var(--text-main)]"><?= $item['name'] ?></h4>
                            <p class="text-xs text-blue-600 font-medium"><?= money($item['price']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 bg-[var(--bg-body)] rounded-lg p-1">
                            <form method="POST" class="contents">
                                <input type="hidden" name="action" value="update_qty">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="qty" value="<?= $item['qty']-1 ?>">
                                <button class="w-8 h-8 flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-white/50 rounded-md">−</button>
                            </form>
                            <span class="text-sm font-bold w-6 text-center"><?= $item['qty'] ?></span>
                            <form method="POST" class="contents">
                                <input type="hidden" name="action" value="update_qty">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="qty" value="<?= $item['qty']+1 ?>">
                                <button class="w-8 h-8 flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-white/50 rounded-md">+</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Totals Section -->
                <?php $totals = calculate_totals($_SESSION['cart']); ?>
                <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)] mt-6">
                    <div class="space-y-3 text-sm mb-6">
                        <div class="flex justify-between text-[var(--text-muted)]"><span>Subtotal</span> <span><?= money($totals['subtotal']) ?></span></div>
                        <div class="flex justify-between text-[var(--text-muted)]"><span>Tax (GST)</span> <span><?= money($totals['tax']) ?></span></div>
                        <div class="flex justify-between text-green-500 font-medium"><span>Savings</span> <span>-<?= money($totals['savings']) ?></span></div>
                        <div class="flex justify-between text-xl font-bold text-[var(--text-main)] pt-4 border-t border-[var(--border-color)]"><span>Total Pay</span> <span><?= money($totals['final']) ?></span></div>
                    </div>

                    <!-- Checkout Form -->
                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <p class="text-xs text-[var(--text-muted)] mb-3 uppercase font-bold tracking-wider">Payment Method</p>
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="UPI" class="peer sr-only" checked>
                                <div class="py-3 text-center border-2 border-[var(--border-color)] rounded-xl text-sm font-semibold text-[var(--text-muted)] peer-checked:border-blue-500 peer-checked:bg-blue-500/5 peer-checked:text-blue-600 transition-all">📱 UPI</div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="Cash" class="peer sr-only">
                                <div class="py-3 text-center border-2 border-[var(--border-color)] rounded-xl text-sm font-semibold text-[var(--text-muted)] peer-checked:border-green-500 peer-checked:bg-green-500/5 peer-checked:text-green-600 transition-all">💵 Cash</div>
                            </label>
                        </div>
                        <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-500/25 transition-all transform hover:scale-[1.02]">Complete Order</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif($tab === 'settings'): ?>
        <!-- TAB: SETTINGS -->
        <div class="p-6 max-w-2xl mx-auto space-y-6 pb-24">
            <h2 class="text-2xl font-bold text-[var(--text-main)] mb-6">Settings</h2>

            <!-- Appearance Section -->
            <div class="panel p-5 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--border-color)] pb-3">
                    <span class="text-blue-500 text-xl">🎨</span>
                    <h3 class="font-bold text-[var(--text-main)]">Appearance</h3>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-[var(--text-main)]">App Theme</p>
                        <p class="text-xs text-[var(--text-muted)]">Switch between Day and Night mode</p>
                    </div>
                    <div class="flex gap-2 bg-[var(--bg-body)] p-1 rounded-lg border border-[var(--border-color)]">
                        <button onclick="setTheme('light')" class="px-4 py-1.5 rounded-md text-xs font-bold transition-all hover:bg-white hover:text-black focus:bg-white focus:text-black text-[var(--text-muted)]">Light</button>
                        <button onclick="setTheme('dark')" class="px-4 py-1.5 rounded-md text-xs font-bold transition-all hover:bg-gray-800 hover:text-white focus:bg-gray-800 focus:text-white text-[var(--text-muted)]">Dark</button>
                    </div>
                </div>
            </div>

            <!-- Security Section -->
            <div class="panel p-5 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--border-color)] pb-3">
                    <span class="text-green-500 text-xl">🔒</span>
                    <h3 class="font-bold text-[var(--text-main)]">Security</h3>
                </div>
                <div x-data="{ locked: false }" class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-[var(--text-main)]">App Lock</p>
                        <p class="text-xs text-[var(--text-muted)]">Require passcode on startup</p>
                    </div>
                    <button @click="locked = !locked" :class="locked ? 'bg-green-500' : 'bg-gray-300'" class="w-12 h-6 rounded-full relative transition-colors duration-300">
                        <div :class="locked ? 'translate-x-6' : 'translate-x-1'" class="w-4 h-4 bg-white rounded-full absolute top-1 transition-transform duration-300 shadow-sm"></div>
                    </button>
                </div>
            </div>

            <!-- AI Help Agent -->
            <div class="panel p-5 rounded-2xl shadow-sm border border-[var(--border-color)]" x-data="{ chatOpen: false }">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--border-color)] pb-3">
                    <span class="text-purple-500 text-xl">🤖</span>
                    <h3 class="font-bold text-[var(--text-main)]">Luminous AI Helper</h3>
                </div>
                
                <div x-show="!chatOpen" class="text-center py-4">
                    <p class="text-sm text-[var(--text-muted)] mb-4">Need help guiding customers? Ask our AI.</p>
                    <button @click="chatOpen = true" class="bg-purple-600 text-white px-6 py-2 rounded-full text-sm font-bold shadow-lg shadow-purple-500/20 hover:bg-purple-700 transition-all">Start Chat</button>
                </div>

                <div x-show="chatOpen" class="space-y-4" style="display: none;">
                    <div class="bg-[var(--bg-body)] p-4 rounded-xl h-48 overflow-y-auto space-y-3 border border-[var(--border-color)]">
                        <div class="flex gap-2">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs">🤖</div>
                            <div class="bg-purple-100 text-purple-900 text-xs p-2 rounded-lg rounded-tl-none max-w-[80%]">
                                Hello! I'm Luminous AI. How can I assist you or your customer today?
                            </div>
                        </div>
                        <div class="flex gap-2 flex-row-reverse">
                             <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs">👤</div>
                             <div class="bg-blue-600 text-white text-xs p-2 rounded-lg rounded-tr-none max-w-[80%]">
                                What is the return policy for shirts?
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs">🤖</div>
                            <div class="bg-purple-100 text-purple-900 text-xs p-2 rounded-lg rounded-tl-none max-w-[80%]">
                                Shirts can be returned within 7 days if tags are intact!
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <input placeholder="Ask something..." class="flex-1 input-field rounded-full px-4 py-2 text-sm outline-none focus:ring-1 focus:ring-purple-500">
                        <button class="w-9 h-9 bg-purple-600 rounded-full text-white flex items-center justify-center">↑</button>
                    </div>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="panel p-5 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--border-color)] pb-3">
                    <span class="text-orange-500 text-xl">📜</span>
                    <h3 class="font-bold text-[var(--text-main)]">Terms & Conditions</h3>
                </div>
                <div class="space-y-4 text-sm text-[var(--text-muted)]">
                    <div class="bg-[var(--bg-body)] p-3 rounded-lg border border-[var(--border-color)]">
                        <h4 class="font-bold text-[var(--text-main)] mb-1">Exchange Policy</h4>
                        <p>Items can be exchanged within <span class="text-orange-500 font-bold">7 days</span> of purchase. Original invoice and price tags are mandatory for all exchanges.</p>
                    </div>
                    <div class="bg-[var(--bg-body)] p-3 rounded-lg border border-[var(--border-color)]">
                        <h4 class="font-bold text-[var(--text-main)] mb-1">Return Criteria</h4>
                        <ul class="list-disc ml-4 space-y-1 text-xs">
                            <li>Item must be unused and unwashed.</li>
                            <li>No returns on innerwear, accessories, or discounted items.</li>
                            <li>Refunds are processed to the original payment source within 5-7 business days.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <div class="h-20 panel border-t border-[var(--border-color)] grid grid-cols-3 shadow-[0_-5px_20px_rgba(0,0,0,0.05)] z-20">
        <form method="POST" class="h-full">
            <input type="hidden" name="action" value="tab">
            <input type="hidden" name="tab" value="scan">
            <button class="w-full h-full flex flex-col items-center justify-center gap-1 transition-colors <?= $tab==='scan'?'text-blue-600':'text-[var(--text-muted)] hover:text-[var(--text-main)]' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><rect x="7" y="7" width="10" height="10" rx="1"></rect></svg>
                <span class="text-xs font-semibold">Scanner</span>
            </button>
        </form>
        <form method="POST" class="h-full relative">
            <input type="hidden" name="action" value="tab">
            <input type="hidden" name="tab" value="cart">
            <button class="w-full h-full flex flex-col items-center justify-center gap-1 transition-colors <?= $tab==='cart'?'text-blue-600':'text-[var(--text-muted)] hover:text-[var(--text-main)]' ?>">
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    <?php if(!empty($_SESSION['cart'])): ?>
                        <span class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-[var(--bg-panel)]"><?= count($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-semibold">My Cart</span>
            </button>
        </form>
        <form method="POST" class="h-full relative">
            <input type="hidden" name="action" value="tab">
            <input type="hidden" name="tab" value="settings">
            <button class="w-full h-full flex flex-col items-center justify-center gap-1 transition-colors <?= $tab==='settings'?'text-blue-600':'text-[var(--text-muted)] hover:text-[var(--text-main)]' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                <span class="text-xs font-semibold">Settings</span>
            </button>
        </form>
    </div>
</div>


<!-- ==================== VIEW: BILL / INVOICE ==================== -->
<?php elseif($view === 'bill'): ?>
<?php
    $tx_id = $_SESSION['current_bill_id'];
    $tx = null;
    foreach($_SESSION['tx'] as $t) if($t['id'] === $tx_id) { $tx = $t; break; }
?>
<div class="min-h-screen bg-[var(--bg-body)] flex flex-col items-center justify-center p-4">
    
    <!-- Action Bar -->
    <div class="w-full max-w-sm flex justify-between items-center mb-6 no-print">
        <form method="POST">
            <?php if(isset($_SESSION['view']) && $_SESSION['view'] === 'admin'): ?>
                <input type="hidden" name="action" value="admin_back">
            <?php else: ?>
                <input type="hidden" name="action" value="back_to_home">
            <?php endif; ?>
            <button class="text-[var(--text-muted)] hover:text-[var(--text-main)] flex items-center gap-2 font-medium px-4 py-2 rounded-lg hover:bg-[var(--bg-panel)] transition-colors">
                <span>← Back</span>
            </button>
        </form>
        <button onclick="window.print()" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/30 flex items-center gap-2">
            <span>🖨️</span> Print Invoice
        </button>
    </div>

    <!-- The Bill Card -->
    <div id="printable-bill" class="bg-white text-black w-full max-w-sm p-8 shadow-2xl relative rounded-none sm:rounded-xl">
        <!-- Receipt Header -->
        <div class="text-center border-b-2 border-dashed border-gray-200 pb-6 mb-6">
            <h1 class="text-3xl font-black uppercase tracking-tight mb-2">Luminous</h1>
            <p class="text-sm font-medium uppercase tracking-widest text-gray-500">Retail POS System</p>
            <div class="mt-4 text-xs text-gray-400 space-y-1">
                <p>Branch: <?= $tx['city'] ?></p>
                <p><?= date('M d, Y • h:i A', strtotime($tx['date'])) ?></p>
                <p class="font-mono">Ref: <?= $tx['id'] ?></p>
            </div>
        </div>

        <!-- Customer -->
        <div class="mb-6 text-sm bg-gray-50 p-3 rounded-lg border border-gray-100">
            <div class="flex justify-between mb-1">
                <span class="text-gray-500">Billed To:</span>
                <span class="font-bold text-gray-900"><?= $tx['customer_name'] ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Contact:</span>
                <span class="font-mono text-gray-900"><?= $tx['customer_phone'] ?></span>
            </div>
        </div>

        <!-- Items -->
        <table class="w-full text-sm mb-6">
            <thead class="border-b border-gray-200 text-gray-500 text-xs uppercase">
                <tr class="text-left">
                    <th class="pb-2 font-bold">Item</th>
                    <th class="pb-2 text-right">Qty</th>
                    <th class="pb-2 text-right">Amt</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php foreach($tx['cart_details'] as $item): ?>
                <tr>
                    <td class="py-2 border-b border-gray-50"><?= $item['name'] ?></td>
                    <td class="py-2 text-right border-b border-gray-50 font-mono"><?= $item['qty'] ?></td>
                    <td class="py-2 text-right border-b border-gray-50 font-medium"><?= number_format($item['price']*$item['qty'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="space-y-1 text-sm text-right">
            <div class="flex justify-between text-gray-500">
                <span>Subtotal</span>
                <span><?= money($tx['totals']['subtotal']) ?></span>
            </div>
            <div class="flex justify-between text-gray-500 text-xs">
                <span>Tax</span>
                <span><?= money($tx['totals']['tax']) ?></span>
            </div>
            <div class="flex justify-between text-green-600 text-xs font-medium">
                <span>Discount</span>
                <span>-<?= money($tx['totals']['savings']) ?></span>
            </div>
            <div class="flex justify-between font-black text-xl mt-4 pt-4 border-t-2 border-gray-900 text-gray-900">
                <span>TOTAL</span>
                <span><?= money($tx['totals']['final']) ?></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 pt-6 border-t border-gray-100">
            <div class="inline-block px-3 py-1 bg-gray-100 rounded text-xs font-bold text-gray-500 mb-2">PAID VIA <?= strtoupper($tx['payment_method']) ?></div>
            <p class="text-[10px] text-gray-400">Thank you for shopping with Luminous!</p>
        </div>
    </div>
</div>


<!-- ==================== VIEW: ADMIN DASHBOARD ==================== -->
<?php else: ?>
<?php
    $all_tx = $_SESSION['tx'];
    $filter = $_SESSION['admin_filter_city'];
    
    // Filter Transactions
    $filtered_tx = ($filter === 'All') 
        ? $all_tx 
        : array_filter($all_tx, fn($t) => $t['city'] === $filter);
    
    $filtered_tx = array_reverse($filtered_tx); // Show newest first

    // Calculate Metrics
    $revenue = 0; $profit = 0; $total_items = 0; $customers = count($filtered_tx);
    $cat_sales = [];
    $prod_sales = [];

    foreach($filtered_tx as $t){
        $revenue += $t['totals']['final'];
        $profit += $t['totals']['profit'];
        
        foreach($t['cart_details'] as $item){
            $total_items += $item['qty'];

            // Category Sales
            $cat = $item['category'] ?? 'General';
            $cat_sales[$cat] = ($cat_sales[$cat] ?? 0) + $item['qty'];
            
            // Product Sales (for Most Liked in filtered city)
            $prod_sales[$item['name']] = ($prod_sales[$item['name']] ?? 0) + $item['qty'];
        }
    }
    arsort($prod_sales);
    
    // Global Data for City Chart (Revenue & Profit)
    $city_revenue = [];
    $city_profit = [];
    foreach($all_tx as $t) {
        $c = $t['city'];
        $city_revenue[$c] = ($city_revenue[$c] ?? 0) + $t['totals']['final'];
        $city_profit[$c] = ($city_profit[$c] ?? 0) + $t['totals']['profit'];
    }
?>
<div class="min-h-screen bg-[var(--bg-body)] text-[var(--text-main)] flex flex-col" x-data="{ liveMonitor: localStorage.getItem('admin_live_monitor') === 'true' }" x-init="$watch('liveMonitor', val => localStorage.setItem('admin_live_monitor', val)); if(liveMonitor) setInterval(() => window.location.reload(), 5000);">
    <!-- Admin Header -->
    <div class="border-b border-[var(--border-color)] p-6 flex flex-col md:flex-row justify-between items-center gap-4 panel sticky top-0 z-30">
        <div>
            <h1 class="font-bold text-2xl tracking-tight flex items-center gap-2">
                Dashboard
                <span x-show="liveMonitor" class="flex h-3 w-3 relative">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
            </h1>
            <p class="text-sm text-[var(--text-muted)]">Real-time Analytics Control Center</p>
        </div>
        
        <div class="flex items-center gap-4">
            <!-- Live Monitor Toggle -->
            <div class="flex items-center gap-2 bg-[var(--bg-body)] px-3 py-1.5 rounded-lg border border-[var(--border-color)]">
                <span class="text-xs font-bold text-[var(--text-muted)] uppercase">Live Monitor</span>
                <button @click="liveMonitor = !liveMonitor; if(liveMonitor) window.location.reload();" 
                        :class="liveMonitor ? 'bg-green-500' : 'bg-gray-300'" class="w-8 h-4 rounded-full relative transition-colors duration-300">
                    <div :class="liveMonitor ? 'translate-x-4' : 'translate-x-0.5'" class="w-3 h-3 bg-white rounded-full absolute top-0.5 transition-transform duration-300 shadow-sm"></div>
                </button>
            </div>

            <!-- Quick Switch -->
            <form method="POST">
                <input type="hidden" name="action" value="switch_to_pos">
                <button class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold px-4 py-2 rounded-lg transition-all flex items-center gap-2 shadow-lg shadow-blue-500/20">
                    <span>⚡</span> Go to POS
                </button>
            </form>

            <!-- Filter -->
            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="action" value="admin_filter">
                <select name="filter_city" onchange="this.form.submit()" class="input-field rounded-lg px-3 py-2 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="All" <?= $filter==='All'?'selected':'' ?>>All Locations</option>
                    <?php foreach($CITIES as $c): ?>
                        <option value="<?= $c ?>" <?= $filter===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <!-- Logout -->
            <form method="POST">
                <input type="hidden" name="action" value="logout">
                <button class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 text-sm font-bold px-4 py-2 rounded-lg transition-colors">Logout</button>
            </form>
        </div>
    </div>

    <div class="p-6 max-w-7xl mx-auto w-full space-y-8">
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)] relative overflow-hidden group hover:-translate-y-1 transition-transform">
                <div class="absolute right-0 top-0 w-32 h-32 bg-blue-500/10 rounded-bl-full -mr-8 -mt-8 transition-all group-hover:bg-blue-500/20"></div>
                <div class="text-[var(--text-muted)] text-xs uppercase font-bold tracking-wider mb-2">Total Revenue</div>
                <div class="text-2xl lg:text-3xl font-black text-[var(--text-main)]"><?= money($revenue) ?></div>
            </div>
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)] relative overflow-hidden group hover:-translate-y-1 transition-transform">
                <div class="absolute right-0 top-0 w-32 h-32 bg-green-500/10 rounded-bl-full -mr-8 -mt-8 transition-all group-hover:bg-green-500/20"></div>
                <div class="text-[var(--text-muted)] text-xs uppercase font-bold tracking-wider mb-2">Net Profit</div>
                <div class="text-2xl lg:text-3xl font-black text-green-500"><?= money($profit) ?></div>
            </div>
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)] relative overflow-hidden group hover:-translate-y-1 transition-transform">
                <div class="absolute right-0 top-0 w-32 h-32 bg-orange-500/10 rounded-bl-full -mr-8 -mt-8 transition-all group-hover:bg-orange-500/20"></div>
                <div class="text-[var(--text-muted)] text-xs uppercase font-bold tracking-wider mb-2">Products Sold</div>
                <div class="text-2xl lg:text-3xl font-black text-orange-500"><?= $total_items ?></div>
            </div>
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)] relative overflow-hidden group hover:-translate-y-1 transition-transform">
                <div class="absolute right-0 top-0 w-32 h-32 bg-purple-500/10 rounded-bl-full -mr-8 -mt-8 transition-all group-hover:bg-purple-500/20"></div>
                <div class="text-[var(--text-muted)] text-xs uppercase font-bold tracking-wider mb-2">Total Customers</div>
                <div class="text-2xl lg:text-3xl font-black text-purple-500"><?= $customers ?></div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Revenue & Profit Chart -->
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <h3 class="font-bold text-lg mb-4 text-[var(--text-main)] flex items-center gap-2">
                    <span>📊</span> City Performance
                    <span class="text-xs font-normal text-[var(--text-muted)] ml-auto">(Revenue vs Profit)</span>
                </h3>
                <div class="relative h-96 w-full"> 
                    <canvas id="cityChart"></canvas>
                </div>
            </div>
            <!-- Category Chart -->
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <h3 class="font-bold text-lg mb-4 text-[var(--text-main)] flex items-center gap-2">
                    <span>🍩</span> Category Preferences
                </h3>
                <div class="relative h-96 w-full flex justify-center">
                    <canvas id="catChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Transactions & Top Products -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Top Products (Filtered by City) -->
            <div class="panel p-6 rounded-2xl shadow-sm border border-[var(--border-color)]">
                <h3 class="font-bold text-lg mb-4 text-[var(--text-main)]">🔥 Top Movers (<?= $filter ?>)</h3>
                <div class="space-y-3">
                    <?php if(empty($prod_sales)): ?>
                        <p class="text-[var(--text-muted)] text-sm italic">No sales yet for this location.</p>
                    <?php else: $i=1; foreach(array_slice($prod_sales, 0, 5) as $name => $qty): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-[var(--bg-body)]">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded bg-blue-100 text-blue-600 text-xs font-bold flex items-center justify-center">#<?= $i++ ?></span>
                                <span class="text-sm font-medium text-[var(--text-main)]"><?= $name ?></span>
                            </div>
                            <span class="text-xs font-bold text-[var(--text-muted)]"><?= $qty ?> sold</span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="lg:col-span-2 panel rounded-2xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                <div class="p-6 border-b border-[var(--border-color)] flex justify-between items-center">
                    <h3 class="font-bold text-lg text-[var(--text-main)]">Recent Transactions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-[var(--bg-body)] text-xs uppercase font-bold text-[var(--text-muted)]">
                            <tr>
                                <th class="p-4">Customer</th>
                                <th class="p-4 text-center">Items</th>
                                <th class="p-4">Amount</th>
                                <th class="p-4">Method</th>
                                <th class="p-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border-color)]">
                            <?php foreach(array_slice($filtered_tx, 0, 8) as $t): ?>
                            <tr class="hover:bg-[var(--bg-body)] transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-[var(--text-main)]"><?= $t['customer_name'] ?></div>
                                    <div class="text-xs text-[var(--text-muted)]"><?= $t['city'] ?> • <?= $t['id'] ?></div>
                                </td>
                                <td class="p-4 text-center font-mono font-bold text-[var(--text-muted)]">
                                    <?= $t['items_count'] ?>
                                </td>
                                <td class="p-4 font-bold text-[var(--text-main)]"><?= money($t['totals']['final']) ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded text-xs font-medium <?= $t['payment_method']=='UPI' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600' ?>">
                                        <?= $t['payment_method'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="view_bill">
                                        <input type="hidden" name="tx_id" value="<?= $t['id'] ?>">
                                        <button class="text-blue-600 hover:text-blue-800 text-xs font-bold border border-blue-200 hover:border-blue-400 px-3 py-1.5 rounded transition-colors">
                                            View Bill
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Logic -->
<script>
    // Get Colors from CSS variables for chart sync
    const style = getComputedStyle(document.body);
    const textColor = style.getPropertyValue('--text-muted').trim();
    const gridColor = style.getPropertyValue('--border-color').trim();

    // Data Injection
    const cityLabels = <?= json_encode(array_keys($city_revenue)) ?>;
    const cityDataRev = <?= json_encode(array_values($city_revenue)) ?>;
    const cityDataProf = <?= json_encode(array_values($city_profit)) ?>;
    const catLabels = <?= json_encode(array_keys($cat_sales)) ?>;
    const catData = <?= json_encode(array_values($cat_sales)) ?>;

    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;

    if(document.getElementById('cityChart')) {
        new Chart(document.getElementById('cityChart'), {
            type: 'bar',
            data: {
                labels: cityLabels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: cityDataRev,
                        backgroundColor: '#3b82f6', // Bright Blue
                        borderRadius: 4,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Profit',
                        data: cityDataProf,
                        backgroundColor: '#10b981', // Bright Green
                        borderRadius: 4,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        grid: { color: gridColor }, 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value;
                            }
                        }
                    },
                    x: { grid: { display: false } }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    if(document.getElementById('catChart')) {
        new Chart(document.getElementById('catChart'), {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catData,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right' } }
            }
        });
    }
</script>
<?php endif; ?>

</body>
</html>