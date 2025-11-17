<?php
require_once __DIR__ . '/../config/config.php';

$userId = requireAuth();

// Get user data
$users = mongoQuery('users', ['_id' => new MongoDB\BSON\ObjectId($userId)]);
$user = !empty($users) ? $users[0] : null;

// Load settings
$settings = json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true);
$pricing = $settings['pricing'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade - Hyperliquid Trading SaaS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="container">
            <a href="/dashboard.php" class="logo">⚡ HyperTrade</a>
            <ul class="nav-links">
                <li><a href="/dashboard.php">Dashboard</a></li>
                <li><a href="/bots.php">My Bots</a></li>
                <li><a href="/create-bot.php">Create Bot</a></li>
                <li><a href="/pay.php">Upgrade</a></li>
                <?php if ($_SESSION['is_admin'] ?? false): ?>
                    <li><a href="/admin/">Admin</a></li>
                <?php endif; ?>
                <li><a href="/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container dashboard">
        <h1>💎 Upgrade Your Account</h1>
        <p class="text-secondary mb-3">Current Tier: <span class="badge badge-info"><?php echo strtoupper($user->tier); ?></span></p>

        <!-- Pricing Tiers -->
        <div class="grid grid-3 mb-4">
            <?php foreach ($pricing as $tierName => $tierData): ?>
                <?php $tierKey = str_replace('_tier', '', $tierName); ?>
                <div class="card <?php echo $user->tier === $tierKey ? 'border-accent' : ''; ?>" style="border-width: 2px;">
                    <div class="card-header text-center">
                        <h2><?php echo strtoupper($tierKey); ?></h2>
                        <?php if ($user->tier === $tierKey): ?>
                            <span class="badge badge-success">Current Plan</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <?php if (isset($tierData['price_month'])): ?>
                                <div class="stat-value" style="font-size: 2.5rem;">
                                    $<?php echo number_format($tierData['price_month'], 2); ?>
                                </div>
                                <div class="text-secondary">per month</div>
                                <div class="text-secondary mt-1" style="font-size: 0.875rem;">
                                    or $<?php echo number_format($tierData['price_year'], 2); ?>/year
                                </div>
                            <?php else: ?>
                                <div class="stat-value" style="font-size: 2.5rem;">FREE</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Max Bots:</strong> <?php echo $tierData['max_bots']; ?>
                        </div>
                        <div class="mb-3">
                            <strong>Max Capital:</strong> $<?php echo number_format($tierData['max_capital_usd']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Features:</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <?php foreach ($tierData['features'] as $feature): ?>
                                    <li><?php echo ucwords(str_replace('_', ' ', $feature)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <?php if ($user->tier !== $tierKey && $tierKey !== 'free'): ?>
                            <button class="btn btn-primary" onclick="selectPlan('<?php echo $tierKey; ?>', 'month')">
                                Upgrade Now
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Payment Modal -->
        <div id="paymentModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Complete Payment</h2>
                    <button class="modal-close" onclick="closePaymentModal()">&times;</button>
                </div>

                <div id="paymentStep1">
                    <h3 class="mb-3">Select Payment Method</h3>

                    <div class="wallet-option" onclick="selectWallet('phantom', 'SOL')">
                        <div class="flex-between">
                            <div>
                                <strong>Phantom Wallet</strong>
                                <div class="text-secondary">Pay with SOL</div>
                            </div>
                            <div>→</div>
                        </div>
                    </div>

                    <div class="wallet-option" onclick="selectWallet('metamask', 'ETH')">
                        <div class="flex-between">
                            <div>
                                <strong>MetaMask</strong>
                                <div class="text-secondary">Pay with ETH</div>
                            </div>
                            <div>→</div>
                        </div>
                    </div>

                    <div class="wallet-option" onclick="selectWallet('metamask', 'USDC')">
                        <div class="flex-between">
                            <div>
                                <strong>MetaMask (USDC)</strong>
                                <div class="text-secondary">Pay with USDC</div>
                            </div>
                            <div>→</div>
                        </div>
                    </div>
                </div>

                <div id="paymentStep2" style="display: none;">
                    <h3 class="mb-3">Confirm Payment</h3>

                    <div class="card">
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Plan:</strong> <span id="selectedPlan"></span>
                            </div>
                            <div class="mb-2">
                                <strong>Amount:</strong> $<span id="selectedAmount"></span> USD
                            </div>
                            <div class="mb-2">
                                <strong>Payment Method:</strong> <span id="selectedWallet"></span>
                            </div>
                            <div class="mb-2">
                                <strong>Send to:</strong>
                                <code id="walletAddress" style="word-break: break-all;"></code>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        Click "Connect Wallet" to complete the payment securely through your wallet.
                    </div>

                    <button class="btn btn-primary" onclick="connectWallet()">
                        Connect Wallet & Pay
                    </button>
                    <button class="btn btn-secondary" onclick="backToStep1()">
                        Back
                    </button>

                    <div id="paymentStatus" style="display: none;" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedTier = '';
        let selectedBilling = '';
        let selectedWalletType = '';
        let selectedCrypto = '';

        function selectPlan(tier, billing) {
            selectedTier = tier;
            selectedBilling = billing;
            document.getElementById('paymentModal').classList.add('active');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
            document.getElementById('paymentStep1').style.display = 'block';
            document.getElementById('paymentStep2').style.display = 'none';
        }

        function selectWallet(walletType, crypto) {
            selectedWalletType = walletType;
            selectedCrypto = crypto;

            // Calculate amount based on selected plan
            const pricing = <?php echo json_encode($pricing); ?>;
            const tierData = pricing[selectedTier + '_tier'];
            const amount = selectedBilling === 'month' ? tierData.price_month : tierData.price_year;

            // Update UI
            document.getElementById('selectedPlan').textContent = selectedTier.toUpperCase() + ' (' + selectedBilling + 'ly)';
            document.getElementById('selectedAmount').textContent = amount.toFixed(2);
            document.getElementById('selectedWallet').textContent = walletType.charAt(0).toUpperCase() + walletType.slice(1) + ' (' + crypto + ')';

            // Set wallet address
            const walletAddress = crypto === 'SOL' ? '<?php echo SOL_WALLET; ?>' : '<?php echo ETH_WALLET; ?>';
            document.getElementById('walletAddress').textContent = walletAddress;

            // Show step 2
            document.getElementById('paymentStep1').style.display = 'none';
            document.getElementById('paymentStep2').style.display = 'block';
        }

        function backToStep1() {
            document.getElementById('paymentStep1').style.display = 'block';
            document.getElementById('paymentStep2').style.display = 'none';
        }

        async function connectWallet() {
            const statusDiv = document.getElementById('paymentStatus');
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '<div class="spinner"></div><p class="text-center">Connecting wallet...</p>';

            try {
                if (selectedWalletType === 'phantom' && selectedCrypto === 'SOL') {
                    await connectPhantom();
                } else if (selectedWalletType === 'metamask') {
                    await connectMetaMask();
                }
            } catch (error) {
                statusDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }

        async function connectPhantom() {
            const statusDiv = document.getElementById('paymentStatus');

            // Check if Phantom is installed
            if (!window.solana || !window.solana.isPhantom) {
                statusDiv.innerHTML = '<div class="alert alert-danger">Phantom wallet not detected. Please install it from <a href="https://phantom.app" target="_blank">phantom.app</a></div>';
                return;
            }

            try {
                // Connect to Phantom
                const response = await window.solana.connect();
                const publicKey = response.publicKey.toString();

                statusDiv.innerHTML = `<div class="alert alert-info">Connected: ${publicKey.substring(0, 8)}...${publicKey.substring(publicKey.length - 8)}</div>`;

                // Get payment details
                const pricing = <?php echo json_encode($pricing); ?>;
                const tierData = pricing[selectedTier + '_tier'];
                const amountUSD = selectedBilling === 'month' ? tierData.price_month : tierData.price_year;

                // Get SOL price (in production, fetch from API)
                const solPrice = await fetchCryptoPrice('SOL');
                const amountSOL = amountUSD / solPrice;

                // Create transaction
                const transaction = await createSOLTransaction(publicKey, amountSOL);

                statusDiv.innerHTML = '<div class="alert alert-info">Please approve the transaction in Phantom...</div>';

                // Send transaction
                const signature = await window.solana.signAndSendTransaction(transaction);

                statusDiv.innerHTML = '<div class="alert alert-info">Transaction sent! Waiting for confirmation...</div>';

                // Record payment
                await recordPayment(signature, publicKey, amountUSD, 'SOL');

                statusDiv.innerHTML = '<div class="alert alert-success">Payment successful! Redirecting...</div>';

                setTimeout(() => {
                    window.location.href = '/dashboard.php?upgraded=1';
                }, 2000);

            } catch (error) {
                statusDiv.innerHTML = `<div class="alert alert-danger">Payment failed: ${error.message}</div>`;
            }
        }

        async function connectMetaMask() {
            const statusDiv = document.getElementById('paymentStatus');

            // Check if MetaMask is installed
            if (!window.ethereum) {
                statusDiv.innerHTML = '<div class="alert alert-danger">MetaMask not detected. Please install it from <a href="https://metamask.io" target="_blank">metamask.io</a></div>';
                return;
            }

            try {
                // Request account access
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                const account = accounts[0];

                statusDiv.innerHTML = `<div class="alert alert-info">Connected: ${account.substring(0, 8)}...${account.substring(account.length - 8)}</div>`;

                // Get payment details
                const pricing = <?php echo json_encode($pricing); ?>;
                const tierData = pricing[selectedTier + '_tier'];
                const amountUSD = selectedBilling === 'month' ? tierData.price_month : tierData.price_year;

                // Get crypto price
                const cryptoPrice = await fetchCryptoPrice(selectedCrypto);
                const amountCrypto = amountUSD / cryptoPrice;

                statusDiv.innerHTML = '<div class="alert alert-info">Please approve the transaction in MetaMask...</div>';

                // Send transaction
                const txHash = await sendETHTransaction(account, amountCrypto);

                statusDiv.innerHTML = '<div class="alert alert-info">Transaction sent! Waiting for confirmation...</div>';

                // Record payment
                await recordPayment(txHash, account, amountUSD, selectedCrypto);

                statusDiv.innerHTML = '<div class="alert alert-success">Payment successful! Redirecting...</div>';

                setTimeout(() => {
                    window.location.href = '/dashboard.php?upgraded=1';
                }, 2000);

            } catch (error) {
                statusDiv.innerHTML = `<div class="alert alert-danger">Payment failed: ${error.message}</div>`;
            }
        }

        async function fetchCryptoPrice(symbol) {
            // In production, fetch from CoinGecko or similar API
            // For demo, return mock prices
            const prices = {
                'SOL': 100,
                'ETH': 2000,
                'USDC': 1
            };
            return prices[symbol] || 1;
        }

        async function createSOLTransaction(fromPubkey, amount) {
            // In production, create actual Solana transaction
            // This is a simplified version
            const destinationPubkey = '<?php echo SOL_WALLET; ?>';

            return {
                feePayer: fromPubkey,
                recentBlockhash: 'mock-blockhash',
                instructions: [{
                    keys: [],
                    programId: 'mock-program',
                    data: Buffer.from([])
                }]
            };
        }

        async function sendETHTransaction(from, amount) {
            const to = '<?php echo ETH_WALLET; ?>';
            const value = '0x' + Math.floor(amount * 1e18).toString(16);

            const txHash = await window.ethereum.request({
                method: 'eth_sendTransaction',
                params: [{
                    from: from,
                    to: to,
                    value: value
                }]
            });

            return txHash;
        }

        async function recordPayment(txHash, wallet, amount, crypto) {
            const response = await fetch('/api/payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'record',
                    tx_hash: txHash,
                    wallet: wallet,
                    amount: amount,
                    crypto: crypto,
                    tier: selectedTier,
                    billing: selectedBilling
                })
            });

            const data = await response.json();
            return data;
        }
    </script>
</body>
</html>
