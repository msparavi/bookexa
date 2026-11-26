<?php
declare(strict_types=1);

if ($route === '/cart') {
    header('X-Robots-Tag: noindex, nofollow');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        $pid = max(0, (int) ($_POST['product_id'] ?? 0));
        if ($act === 'update_qty' && $pid > 0) {
            $res = shop_cart_set_qty($pid, (int) ($_POST['quantity'] ?? 1));
            flash(!empty($res['ok']) ? 'success' : 'danger', (string) ($res['message'] ?? (current_lang() === 'bn' ? 'কার্ট আপডেট হয়েছে।' : 'Cart updated.')));
        } elseif ($act === 'remove_item' && $pid > 0) {
            shop_cart_set_qty($pid, 0);
            flash('success', current_lang() === 'bn' ? 'আইটেম সরানো হয়েছে।' : 'Item removed from cart.');
        } elseif ($act === 'update_shipping') {
            shop_cart_set_shipping((string) ($_POST['delivery_area'] ?? 'inside_dhaka'), (string) ($_POST['district'] ?? 'Dhaka'));
            flash('success', current_lang() === 'bn' ? 'ডেলিভারি এলাকা আপডেট হয়েছে।' : 'Delivery zone updated.');
        } elseif ($act === 'apply_coupon') {
            $res = shop_cart_apply_coupon((string) ($_POST['coupon_code'] ?? ''));
            flash(!empty($res['ok']) ? 'success' : 'danger', (string) ($res['message'] ?? 'Updated.'));
        }
        redirect('/cart');
    }
    $summary = shop_cart_summary();
    render_public('shop_cart.php', [
        'summary'       => $summary,
        'pageTitle'     => t('nav_cart'),
        'activeNav'     => 'cart',
        'robotsNoIndex' => true,
    ]);
}


if ($route === '/login') {
    header('X-Robots-Tag: noindex, nofollow');
    if (is_logged_in()) {
        redirect('/library');
    }
    $error = null;
    $info = null;
    $authMode = 'login';
    $devices = [];
    $otpPhone = '';
    $otpStep = 'phone';
    $otpCooldown = 0;
    $rawNext = (string) ($_REQUEST['next'] ?? $_REQUEST['return'] ?? $_REQUEST['redirect'] ?? '');
    $next = $rawNext !== '' ? safe_internal_path($rawNext, '/library') : '';
    $redirectAfterLogin = static function () use ($next): void {
        redirect($next !== '' ? safe_internal_path($next, '/library') : '/library');
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? 'password_login');
        if ($act === 'replace_device') {
            $pending = $_SESSION['pending_device_auth'] ?? null;
            $devId = (int) ($_POST['device_id'] ?? 0);
            if (!is_array($pending) || empty($pending['user_id']) || (int) ($pending['expires_at'] ?? 0) < time()) {
                unset($_SESSION['pending_device_auth']);
                $error = current_lang() === 'bn' ? 'সেশনের মেয়াদ শেষ। আবার লগইন করুন।' : 'Verification session expired. Please sign in again.';
            } else {
                $pendingUser = db_one('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $pending['user_id']]);
                if (!$pendingUser || $devId <= 0) {
                    $error = current_lang() === 'bn' ? 'ডিভাইস পাওয়া যায়নি।' : 'Invalid device selection.';
                } else {
                    unset($_SESSION['pending_device_auth']);
                    login_user_session($pendingUser, $devId);
                    $redirectAfterLogin();
                }
            }
        } elseif ($act === 'send_login_otp') {
            $authMode = 'otp';
            if (!sms_gateway_enabled()) {
                $error = current_lang() === 'bn' ? 'SMS OTP লগইন বর্তমানে বন্ধ আছে।' : 'SMS OTP login is currently disabled.';
            } else {
                $rawPhone = trim((string) ($_POST['phone'] ?? ''));
                $normPhone = normalize_bd_phone($rawPhone);
                $otpPhone = $normPhone !== '' ? $normPhone : $rawPhone;
                if ($otpPhone === '' || strlen(preg_replace('/\D/', '', $otpPhone) ?? '') < 8) {
                    $error = current_lang() === 'bn' ? 'সঠিক মোবাইল নম্বর দিন।' : 'Enter a valid phone number.';
                } else {
                    $rl = sms_otp_rate_check($otpPhone, client_ip());
                    if (empty($rl['ok'])) {
                        $error = (string) ($rl['message'] ?? 'Too many OTP requests.');
                        $otpCooldown = (int) ($rl['retry_after'] ?? 60);
                        if (($rl['code'] ?? '') === 'COOLDOWN') {
                            $otpStep = 'code';
                        }
                    } elseif (sms_daily_budget_remaining() <= 0) {
                        notify_admin_sms_budget_exhausted(sms_daily_budget());
                        $error = current_lang() === 'bn'
                            ? 'দৈনিক SMS সীমা পূর্ণ হয়েছে। পরে আবার চেষ্টা করুন বা পাসওয়ার্ড দিয়ে লগইন করুন।'
                            : 'OTP service is temporarily unavailable due to daily limit. Please try again later or sign in with password.';
                    } else {
                        $u = find_user_by_identifier($otpPhone);
                        $sentOk = true;
                        if ($u && (int) $u['is_active']) {
                            $otp = issue_otp_verification((int) $u['id'], 'phone_login', $otpPhone);
                            $smsRes = sms_gateway_send($otpPhone, "Your Bookexa login OTP is {$otp}. Valid for 10 minutes.", $otp);
                            if (empty($smsRes['ok'])) {
                                $sentOk = false;
                                $error = (($smsRes['code'] ?? '') === 'BUDGET_EXCEEDED')
                                    ? (current_lang() === 'bn' ? 'দৈনিক SMS সীমা পূর্ণ হয়েছে। পরে চেষ্টা করুন।' : 'OTP service temporarily unavailable due to daily limit.')
                                    : ($smsRes['error'] ?? $smsRes['message'] ?? 'Failed to send OTP.');
                            }
                        }
                        if ($error === null && $sentOk) {
                            $otpStep = 'code';
                            $otpCooldown = 60;
                            $info = current_lang() === 'bn'
                                ? 'যদি এই নম্বরে নিবন্ধিত অ্যাকাউন্ট থাকে, তবে ৬ অঙ্কের OTP পাঠানো হয়েছে।'
                                : 'If an active account is registered with this phone number, a 6-digit OTP has been sent.';
                        }
                    }
                }
            }
        } elseif ($act === 'verify_login_otp') {
            $authMode = 'otp';
            $otpStep = 'code';
            $rawPhone = trim((string) ($_POST['phone'] ?? ''));
            $normPhone = normalize_bd_phone($rawPhone);
            $otpPhone = $normPhone !== '' ? $normPhone : $rawPhone;
            $code = trim((string) ($_POST['otp_code'] ?? ''));
            $u = find_user_by_identifier($otpPhone);
            $vOtp = ($u && (int) $u['is_active']) ? verify_otp_code('phone_login', $otpPhone, $code) : ['ok' => false];
            if (!$u || !(int) $u['is_active'] || empty($vOtp['ok'])) {
                $error = (string) ($vOtp['message'] ?? (current_lang() === 'bn' ? 'OTP কোডটি সঠিক নয় বা মেয়াদ শেষ।' : 'Invalid or expired OTP code.'));
            } else {
                $limitHit = check_device_limit_or_prepare($u);
                if ($limitHit !== null) {
                    $_SESSION['pending_device_auth'] = ['user_id' => (int) $u['id'], 'expires_at' => time() + 300];
                    $authMode = 'device_limit';
                    $devices = $limitHit['devices'];
                    $error = $limitHit['error'];
                } else {
                    login_user_session($u);
                    $redirectAfterLogin();
                }
            }
        } else {
            $res = attempt_user_login((string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($res['ok']) {
                $redirectAfterLogin();
            }
            if (($res['code'] ?? '') === 'DEVICE_LIMIT') {
                $_SESSION['pending_device_auth'] = ['user_id' => (int) $res['user']['id'], 'expires_at' => time() + 300];
                $authMode = 'device_limit';
                $devices = $res['devices'];
            }
            $error = $res['error'];
        }
    } elseif (isset($_GET['mode']) && $_GET['mode'] === 'otp' && sms_gateway_enabled()) {
        $authMode = 'otp';
    }
    render_public('auth.php', [
        'authMode'    => $authMode,
        'error'       => $error,
        'info'        => $info,
        'next'        => $next,
        'devices'     => $devices,
        'otpPhone'    => $otpPhone,
        'otpStep'     => $otpStep,
        'otpCooldown' => $otpCooldown,
        'pageTitle'     => t('nav_login'),
        'activeNav'     => 'account',
        'robotsNoIndex' => true,
    ]);
}

if ($route === '/register') {
    header('X-Robots-Tag: noindex, nofollow');
    if (is_logged_in()) {
        redirect('/library');
    }
    $error = null;
    $rawRegNext = (string) ($_REQUEST['next'] ?? $_REQUEST['return'] ?? $_REQUEST['redirect'] ?? '');
    $regNext = $rawRegNext !== '' ? safe_internal_path($rawRegNext, '/library') : '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $res = register_user_account(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if ($res['ok']) {
            flash('success', current_lang() === 'bn' ? 'স্বাগতম! আপনার অ্যাকাউন্ট তৈরি হয়েছে।' : 'Welcome! Your Bookexa account is ready.');
            redirect($regNext !== '' ? safe_internal_path($regNext, '/library') : '/library');
        }
        $error = $res['error'];
    }
    render_public('auth.php', ['authMode' => 'register', 'error' => $error, 'next' => $regNext, 'pageTitle' => 'Register', 'activeNav' => 'account', 'robotsNoIndex' => true]);
}

if ($route === '/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user_only();
    flash('success', current_lang() === 'bn' ? 'সফলভাবে লগআউট হয়েছেন।' : 'Logged out safely.');
    $rawLogoutNext = (string) ($_REQUEST['next'] ?? $_REQUEST['return'] ?? $_REQUEST['redirect'] ?? '');
    $logoutTarget = $rawLogoutNext !== '' ? safe_internal_path($rawLogoutNext, '/library') : '/';
    redirect($logoutTarget);
}

if ($route === '/forgot-password') {
    header('X-Robots-Tag: noindex, nofollow');
    $error = null;
    $info = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $id = trim((string) ($_POST['identifier'] ?? ''));
        $u = db_one('SELECT id, email FROM users WHERE (email = ? OR phone = ?) AND is_active = 1 LIMIT 1', [strtolower($id), $id]);
        if ($u) {
            $rawTok = bin2hex(random_bytes(32));
            $hash = hash('sha256', $rawTok);
            $exp = date('Y-m-d H:i:s', time() + 1800);
            db_exec('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)', [(int) $u['id'], $hash, $exp]);
            $resetLink = BASE_URL . '/reset-password?token=' . $rawTok;
            send_bookexa_template_mail(
                (string) $u['email'],
                site_setting('site_name', 'Bookexa') . ' — Password Reset',
                'Reset Your Password',
                '<p>We received a request to reset your Bookexa account password. This link is valid for 30 minutes:</p>',
                $resetLink,
                'Reset Password'
            );
            if (app_config('app_env') === 'local') {
                $info = 'Reset link (local dev preview): <a href="' . e($resetLink) . '" style="text-decoration:underline;font-weight:700;">' . e($resetLink) . '</a>';
            } else {
                $info = current_lang() === 'bn' ? 'পাসওয়ার্ড রিসেট নির্দেশনা পাঠানো হয়েছে।' : 'If an account matches, reset instructions have been sent.';
            }
        } else {
            $info = current_lang() === 'bn' ? 'যদি অ্যাকাউন্ট থেকে থাকে তবে রিসেট নির্দেশনা পাঠানো হয়েছে।' : 'If an account matches, reset instructions have been sent.';
        }
    }
    render_public('auth.php', ['authMode' => 'forgot', 'error' => $error, 'info' => $info, 'pageTitle' => 'Reset Password', 'activeNav' => 'account', 'robotsNoIndex' => true]);
}

if ($route === '/reset-password') {
    header('X-Robots-Tag: noindex, nofollow');
    $token = trim((string) ($_REQUEST['token'] ?? ''));
    $error = null;
    $row = ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token))
        ? db_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL LIMIT 1', [hash('sha256', $token)])
        : null;

    if (!$row || strtotime($row['expires_at']) < time()) {
        $error = current_lang() === 'bn' ? 'রিসেট লিংকটি অবৈধ অথবা মেয়াদ শেষ হয়েছে।' : 'This reset link is invalid or expired.';
        render_public('auth.php', ['authMode' => 'forgot', 'error' => $error, 'info' => null, 'pageTitle' => 'Reset Password', 'robotsNoIndex' => true]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $newPw = (string) ($_POST['password'] ?? '');
        if (strlen($newPw) < 6) {
            $error = current_lang() === 'bn' ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            db_exec('UPDATE users SET password_hash = ?, active_session_token = NULL WHERE id = ?', [$hash, (int) $row['user_id']]);
            db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
            flash('success', current_lang() === 'bn' ? 'পাসওয়ার্ড আপডেট হয়েছে। এখন লগইন করুন।' : 'Password updated! Please log in.');
            redirect('/login');
        }
    }
    render_public('auth.php', ['authMode' => 'reset', 'error' => $error, 'resetToken' => $token, 'pageTitle' => 'Set New Password', 'activeNav' => 'account', 'robotsNoIndex' => true]);
}

// ----------------------------------------------------------------------------
// User Area: Profile, My Library, My Orders
// ----------------------------------------------------------------------------
if ($route === '/devices') {
    header('X-Robots-Tag: noindex, nofollow');
    redirect('/profile#my-devices');
}

if ($route === '/profile') {
    header('X-Robots-Tag: noindex, nofollow');
    $user = require_login('/profile');
    $uid = (int) $user['id'];
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        if ($act === 'update_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $error = current_lang() === 'bn' ? 'নাম দেওয়া আবশ্যক।' : 'Name is required.';
            } else {
                db_exec('UPDATE users SET name = ? WHERE id = ?', [$name, $uid]);
                flash('success', current_lang() === 'bn' ? 'প্রোফাইল নাম আপডেট হয়েছে।' : 'Profile name updated.');
                redirect('/profile');
            }
        } elseif ($act === 'request_contact_verify') {
            $kind = ($_POST['contact_type'] ?? 'email') === 'phone' ? 'phone' : 'email';
            $rawVal = trim((string) ($_POST['new_value'] ?? ''));
            if (!user_rate_limit_check('otp_prof:u:' . $uid, 5, 600)) {
                $error = current_lang() === 'bn' ? 'অনেক দ্রুত চেষ্টা করছেন। কিছুক্ষণ পর আবার চেষ্টা করুন।' : 'Too many verification requests. Please wait 10 minutes.';
            } elseif ($kind === 'email') {
                $val = strtolower($rawVal);
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $error = current_lang() === 'bn' ? 'সঠিক ইমেইল ঠিকানা দিন।' : 'Enter a valid email address.';
                } elseif (db_one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$val, $uid])) {
                    $error = current_lang() === 'bn' ? 'এই ইমেইলটি অন্য অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'That email is already in use.';
                } else {
                    $otp = issue_otp_verification($uid, 'change_email', $val);
                    send_bookexa_template_mail(
                        $val,
                        site_setting('site_name', 'Bookexa') . ' — Verify Your New Email',
                        'Email Change Verification',
                        '<p>Use the 6-digit verification code below to confirm changing your account email (valid 10 minutes):</p>'
                        . '<p style="font-size:24px;font-weight:700;letter-spacing:4px;">' . e($otp) . '</p>'
                    );
                    $_SESSION['pending_contact_change'] = ['type' => 'email', 'target' => $val, 'exp' => time() + 600];
                    $msg = current_lang() === 'bn' ? 'আপনার নতুন ইমেইলে ৬ অঙ্কের ভেরিফিকেশন কোড পাঠানো হয়েছে।' : 'Verification code sent to your new email.';
                    if (app_config('app_env') === 'local' && empty(site_setting('smtp_host', ''))) {
                        $msg .= ' (Local OTP: ' . $otp . ')';
                    }
                    flash('info', $msg);
                    redirect('/profile#contact-verify');
                }
            } else {
                $norm = normalize_bd_phone($rawVal);
                $val = $norm !== '' ? $norm : $rawVal;
                if ($val === '' || strlen(preg_replace('/\D/', '', $val)) < 8) {
                    $error = current_lang() === 'bn' ? 'সঠিক মোবাইল নম্বর দিন।' : 'Enter a valid phone number.';
                } elseif (db_one('SELECT id FROM users WHERE (phone = ? OR phone = ?) AND id <> ? LIMIT 1', [$val, $rawVal, $uid])) {
                    $error = current_lang() === 'bn' ? 'এই মোবাইল নম্বরটি অন্য অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'That phone number is already in use.';
                } elseif (sms_gateway_enabled() && empty(($rl = sms_otp_rate_check($val, client_ip()))['ok'])) {
                    $error = (string) ($rl['message'] ?? 'Too many OTP requests.');
                } else {
                    $otp = issue_otp_verification($uid, 'change_phone', $val);
                    $smsOk = true;
                    if (sms_gateway_enabled()) {
                        $smsRes = sms_gateway_send($val, "Bookexa phone verification OTP: {$otp}", $otp);
                        if (empty($smsRes['ok'])) {
                            $smsOk = false;
                            $error = ($smsRes['code'] ?? '') === 'BUDGET_EXCEEDED'
                                ? (current_lang() === 'bn' ? 'দৈনিক SMS সীমা পূর্ণ হয়েছে। পরে চেষ্টা করুন।' : 'Daily SMS limit exceeded. Try again later.')
                                : ($smsRes['error'] ?? 'Failed to send verification SMS.');
                        }
                    }
                    if ($smsOk) {
                        $_SESSION['pending_contact_change'] = ['type' => 'phone', 'target' => $val, 'exp' => time() + 600];
                        $msg = current_lang() === 'bn' ? 'ভেরিফিকেশন কোড তৈরি হয়েছে। নিচে কোডটি লিখুন।' : 'Phone verification code generated. Enter it below to confirm.';
                        if (app_config('app_env') === 'local' || !sms_gateway_enabled() || site_setting('sms_gateway_sandbox', '1') === '1') {
                            $msg .= ' (OTP: ' . $otp . ')';
                        }
                        flash('info', $msg);
                        redirect('/profile#contact-verify');
                    }
                }
            }
        } elseif ($act === 'confirm_contact_verify') {
            $pending = $_SESSION['pending_contact_change'] ?? null;
            $code = trim((string) ($_POST['otp_code'] ?? ''));
            if (!is_array($pending) || (int) ($pending['exp'] ?? 0) < time()) {
                unset($_SESSION['pending_contact_change']);
                $error = current_lang() === 'bn' ? 'ভেরিফিকেশন মেয়াদ শেষ। আবার কোড পাঠান।' : 'Verification request expired. Please request a new code.';
            } else {
                $kind = $pending['type'] === 'phone' ? 'change_phone' : 'change_email';
                $target = (string) $pending['target'];
                $vRes = verify_otp_code($kind, $target, $code);
                if (empty($vRes['ok'])) {
                    $error = (string) ($vRes['message'] ?? (current_lang() === 'bn' ? 'ভেরিফিকেশন কোড সঠিক নয়।' : 'Invalid verification code.'));
                } else {
                    if ($pending['type'] === 'email') {
                        db_exec('UPDATE users SET email = ? WHERE id = ?', [$target, $uid]);
                        flash('success', current_lang() === 'bn' ? 'ইমেইল সফলভাবে যাচাই ও আপডেট হয়েছে।' : 'Email verified and updated.');
                    } else {
                        db_exec('UPDATE users SET phone = ? WHERE id = ?', [$target, $uid]);
                        flash('success', current_lang() === 'bn' ? 'মোবাইল নম্বর সফলভাবে যাচাই ও আপডেট হয়েছে।' : 'Phone number verified and updated.');
                    }
                    unset($_SESSION['pending_contact_change']);
                    redirect('/profile#contact-verify');
                }
            }
        } elseif ($act === 'revoke_device') {
            $devId = (int) ($_POST['device_id'] ?? 0);
            if ($devId > 0 && revoke_user_device($uid, $devId)) {
                flash('success', current_lang() === 'bn' ? 'ডিভাইসটি সাইন আউট করা হয়েছে।' : 'Device signed out successfully.');
            }
            redirect('/profile#my-devices');
        } elseif ($act === 'download_my_data') {
            $export = [
                'exported_at'      => date('c'),
                'profile'          => db_one('SELECT id, name, email, phone, reader_theme, reader_zoom, created_at FROM users WHERE id = ?', [$uid]),
                'devices'          => db_all('SELECT device_label, ip_address, last_active_at, created_at FROM user_devices WHERE user_id = ? ORDER BY last_active_at DESC', [$uid]),
                'library'          => db_all('SELECT i.slug, i.title_en, i.title_bn, l.granted_at FROM library l JOIN items i ON i.id = l.item_id WHERE l.user_id = ?', [$uid]),
                'wishlist'         => db_all('SELECT i.slug, i.title_en, i.title_bn, w.created_at FROM wishlist w JOIN items i ON i.id = w.item_id WHERE w.user_id = ?', [$uid]),
                'orders'           => db_all('SELECT order_code, amount, discount_amount, coupon_code, payment_method, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC', [$uid]),
                'reviews'          => db_all('SELECT i.slug, r.rating, r.comment, r.status, r.created_at FROM reviews r JOIN items i ON i.id = r.item_id WHERE r.user_id = ?', [$uid]),
                'reading_progress' => db_all('SELECT i.slug, rp.last_page, rp.total_pages, rp.time_spent_sec, rp.is_finished, rp.updated_at FROM reading_progress rp JOIN items i ON i.id = rp.item_id WHERE rp.user_id = ?', [$uid]),
                'bookmarks'        => db_all('SELECT i.slug, b.page_num, b.label, b.created_at FROM reader_bookmarks b JOIN items i ON i.id = b.item_id WHERE b.user_id = ? ORDER BY b.id DESC', [$uid]),
            ];
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="bookexa-data-user-' . $uid . '-' . date('Ymd') . '.json"');
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } elseif ($act === 'delete_account') {
            $curr = (string) ($_POST['current_password'] ?? '');
            $confirmWord = strtoupper(trim((string) ($_POST['confirm_delete'] ?? '')));
            $fullUser = db_one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
            if (!$fullUser || !password_verify($curr, $fullUser['password_hash'])) {
                $error = current_lang() === 'bn' ? 'সঠিক বর্তমান পাসওয়ার্ড দিন।' : 'Current password is incorrect.';
            } elseif ($confirmWord !== 'DELETE') {
                $error = current_lang() === 'bn' ? 'নিশ্চিত করতে DELETE লিখুন।' : 'Please type DELETE to confirm account deletion.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    foreach ([
                        'DELETE FROM user_devices WHERE user_id = ?',
                        'DELETE FROM reader_bookmarks WHERE user_id = ?',
                        'DELETE FROM recently_viewed WHERE user_id = ?',
                        'DELETE FROM reading_progress WHERE user_id = ?',
                        'DELETE FROM library WHERE user_id = ?',
                        'DELETE FROM wishlist WHERE user_id = ?',
                        'DELETE FROM reviews WHERE user_id = ?',
                        'DELETE FROM notifications WHERE user_id = ?',
                        'DELETE FROM otp_verifications WHERE user_id = ?',
                        'DELETE FROM password_resets WHERE user_id = ?',
                        'DELETE FROM orders WHERE user_id = ?',
                        'DELETE FROM users WHERE id = ?',
                    ] as $delSql) {
                        db_exec($delSql, [$uid]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
                    session_destroy();
                    session_start();
                }
                flash('info', current_lang() === 'bn' ? 'আপনার অ্যাকাউন্ট স্থায়ীভাবে মুছে ফেলা হয়েছে।' : 'Your account and personal data have been permanently deleted.');
                redirect('/');
            }
        } elseif ($act === 'change_password') {
            $curr = (string) ($_POST['current_password'] ?? '');
            $nextPw = (string) ($_POST['new_password'] ?? '');
            $fullUser = db_one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
            if (!$fullUser || !password_verify($curr, $fullUser['password_hash'])) {
                $error = current_lang() === 'bn' ? 'বর্তমান পাসওয়ার্ড সঠিক নয়।' : 'Current password is incorrect.';
            } elseif (strlen($nextPw) < 6) {
                $error = current_lang() === 'bn' ? 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'New password must be at least 6 characters.';
            } else {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($nextPw, PASSWORD_BCRYPT), $uid]);
                flash('success', current_lang() === 'bn' ? 'পাসওয়ার্ড পরিবর্তিত হয়েছে।' : 'Password changed successfully.');
                redirect('/profile');
            }
        }
    }
    $devices = list_user_devices($uid);
    render_public('profile.php', [
        'user'             => $user,
        'error'            => $error,
        'devices'          => $devices,
        'currentDevToken'  => (string) ($_SESSION['device_token'] ?? ''),
        'pendingContact'   => $_SESSION['pending_contact_change'] ?? null,
        'pageTitle'        => 'Profile',
        'activeNav'        => 'account',
        'robotsNoIndex'    => true,
    ]);
}

if ($route === '/library') {
    header('X-Robots-Tag: noindex, nofollow');
    $user = require_login('/library');
    $uid = (int) $user['id'];
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    $filters = browse_extract_filters($_GET);
    $res = browse_query_items(['mode' => 'library', 'user_id' => $uid], $filters);
    preload_items_card_meta($res['items']);
    $readingStats = user_reading_stats($uid);
    render_public('library.php', [
        'user'          => $user,
        'libraryItems'  => $res['items'],
        'filters'       => $filters,
        'page'          => $res['page'],
        'totalPages'    => $res['totalPages'],
        'totalItems'    => $res['total'],
        'readingStats'  => $readingStats,
        'pageTitle'     => t('nav_library'),
        'activeNav'     => 'library',
        'robotsNoIndex' => true,
    ]);
}

if (preg_match('#^/orders/([^/]+)/invoice$#', $route, $m)) {
    header('X-Robots-Tag: noindex, nofollow');
    $orderCode = rawurldecode($m[1]);
    $adm = current_admin();
    $usr = $adm ? null : require_login('/orders/' . rawurlencode($orderCode) . '/invoice');
    $order = db_one(
        'SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
                i.title_bn, i.title_en, i.slug
         FROM orders o
         JOIN users u ON u.id = o.user_id
         JOIN items i ON i.id = o.item_id
         WHERE o.order_code = ? LIMIT 1',
        [$orderCode]
    );
    if (!$order) {
        render_error_page(404);
    }
    if (!$adm && (int) $order['user_id'] !== (int) $usr['id']) {
        render_error_page(403);
    }
    header('X-Robots-Tag: noindex, nofollow');
    require APP_ROOT . '/pages/invoice.php';
    exit;
}

if ($route === '/orders') {
    header('X-Robots-Tag: noindex, nofollow');
    $user = require_login('/orders');
    $uid  = (int) $user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');

        // Physical Book Orders actions
        if (in_array($act, ['shop_cancel_order', 'shop_reorder', 'shop_request_return'], true)) {
            $soId = max(0, (int) ($_POST['shop_order_id'] ?? 0));
            $ownShop = $soId > 0 ? db_one('SELECT * FROM shop_orders WHERE id = ? AND user_id = ? LIMIT 1', [$soId, $uid]) : null;
            if (!$ownShop) {
                flash('danger', current_lang() === 'bn' ? 'অর্ডার খুঁজে পাওয়া যায়নি।' : 'Order not found.');
                redirect('/orders');
            }
            if ($act === 'shop_cancel_order') {
                if ($ownShop['status'] !== 'pending') {
                    flash('warning', current_lang() === 'bn' ? 'শুধুমাত্র অপেক্ষমাণ অর্ডার বাতিল করা যায়।' : 'Only pending orders can be cancelled.');
                } else {
                    shop_transition_order_status($soId, 'cancelled', null, ['admin_note' => 'Cancelled by customer']);
                    flash('success', current_lang() === 'bn' ? 'অর্ডার #' . $ownShop['order_code'] . ' বাতিল হয়েছে।' : 'Order #' . $ownShop['order_code'] . ' cancelled.');
                }
                redirect('/orders#physical-orders');
            }
            if ($act === 'shop_reorder') {
                $rRes = shop_reorder_to_cart($soId, $uid);
                flash(!empty($rRes['ok']) ? 'success' : 'danger', (string) ($rRes['message'] ?? ''));
                redirect(!empty($rRes['ok']) ? '/cart' : '/orders#physical-orders');
            }
            if ($act === 'shop_request_return') {
                $retRes = shop_request_return($soId, $uid, (string) ($_POST['return_reason'] ?? ''));
                flash(!empty($retRes['ok']) ? 'success' : 'danger', (string) ($retRes['message'] ?? ''));
                redirect('/orders#physical-orders');
            }
        }

        $oid = (int) ($_POST['order_id'] ?? 0);
        $ownOrder = $oid > 0 ? db_one('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1', [$oid, $uid]) : null;

        if (!$ownOrder) {
            flash('danger', current_lang() === 'bn' ? 'অর্ডার খুঁজে পাওয়া যায়নি।' : 'Order not found.');
            redirect('/orders');
        }

        if ($act === 'cancel_order') {
            if ($ownOrder['status'] !== 'pending') {
                flash('warning', current_lang() === 'bn' ? 'শুধুমাত্র অপেক্ষমাণ অর্ডার বাতিল করা যায়।' : 'Only pending orders can be cancelled.');
                redirect('/orders');
            }
            apply_order_status_transition($oid, 'cancelled', null, 'Cancelled by customer');
            flash('success', current_lang() === 'bn' ? 'অর্ডার #' . $ownOrder['order_code'] . ' বাতিল হয়েছে।' : 'Order #' . $ownOrder['order_code'] . ' cancelled.');
            redirect('/orders');
        }

        if ($act === 'resubmit_order') {
            if ($ownOrder['status'] !== 'rejected' || (float) $ownOrder['amount'] <= 0) {
                flash('danger', current_lang() === 'bn' ? 'এই অর্ডারটি পুনরায় জমা দেওয়ার উপযুক্ত নয়।' : 'Only rejected paid orders can be fixed and resubmitted.');
                redirect('/orders');
            }
            $method = (string) ($_POST['payment_method'] ?? 'bkash');
            if (!in_array($method, ['bkash', 'nagad', 'rocket'], true)) {
                $method = 'bkash';
            }
            $sender = trim((string) ($_POST['sender_number'] ?? ''));
            $trxId  = normalize_transaction_id((string) ($_POST['transaction_id'] ?? ''));
            if ($sender === '' || $trxId === '' || str_starts_with($trxId, 'COUPON')) {
                flash('danger', current_lang() === 'bn' ? 'সঠিক প্রেরক নম্বর এবং Transaction ID দিন।' : 'Valid sender number and Transaction ID are required.');
                redirect('/orders');
            }

            $newProof = null;
            try {
                $newProof = save_payment_screenshot_upload('payment_screenshot');
            } catch (Throwable $upEx) {
                flash('danger', $upEx->getMessage());
                redirect('/orders');
            }

            try {
                db()->beginTransaction();
                $locked = db_one('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE', [$oid, $uid]);
                if (!$locked || $locked['status'] !== 'rejected') {
                    db()->rollBack();
                    flash('danger', current_lang() === 'bn' ? 'অর্ডারের অবস্থা পরিবর্তিত হয়েছে।' : 'Order state changed.');
                    redirect('/orders');
                }
                $dupTrx = db_one(
                    'SELECT id FROM orders WHERE payment_method = ? AND transaction_id = ? AND id <> ? LIMIT 1',
                    [$method, substr($trxId, 0, 80), $oid]
                );
                if ($dupTrx) {
                    db()->rollBack();
                    flash('danger', current_lang() === 'bn' ? 'এই Transaction ID ইতিমধ্যে অন্য অর্ডারে ব্যবহৃত হয়েছে।' : 'This Transaction ID is already used by another order.');
                    redirect('/orders');
                }
                if (!empty($locked['coupon_id']) && (int) ($locked['coupon_decremented'] ?? 0) === 1) {
                    $resUsed = db_exec(
                        'UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= NOW()) AND (usage_limit IS NULL OR used_count < usage_limit)',
                        [(int) $locked['coupon_id']]
                    );
                    if ($resUsed !== 1) {
                        db()->rollBack();
                        flash('danger', current_lang() === 'bn' ? 'পূর্ববর্তী কুপনের মেয়াদ বা সীমা শেষ হয়ে গেছে — অনুগ্রহ করে নতুন অর্ডার করুন।' : 'The coupon used on this order is no longer available — please place a new order.');
                        redirect('/orders');
                    }
                }
                db_exec(
                    'UPDATE orders
                     SET payment_method = ?, sender_number = ?, transaction_id = ?, payment_screenshot = COALESCE(?, payment_screenshot),
                         status = "pending", coupon_decremented = 0, admin_note = NULL, reviewed_by = NULL, reviewed_at = NULL
                     WHERE id = ?',
                    [$method, mb_substr($sender, 0, 40), substr($trxId, 0, 80), $newProof, $oid]
                );
                db()->commit();
            } catch (PDOException $pe) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('danger', current_lang() === 'bn' ? 'Transaction ID ডুপ্লিকেট হয়েছে।' : 'Duplicate Transaction ID detected.');
                redirect('/orders');
            }

            notify_order_status_change($oid, 'pending');
            flash('success', current_lang() === 'bn' ? 'সংশোধিত পেমেন্ট তথ্য জমা হয়েছে! যাচাইয়ের জন্য অপেক্ষা করুন।' : 'Updated payment details resubmitted for admin review.');
            redirect('/orders');
        }
    }

    $orders = db_all(
        'SELECT o.*, i.title_bn, i.title_en, i.slug
         FROM orders o JOIN items i ON i.id = o.item_id
         WHERE o.user_id = ? ORDER BY o.id DESC',
        [$uid]
    );
    $shopOrders = db_all('SELECT * FROM shop_orders WHERE user_id = ? ORDER BY id DESC', [$uid]);
    $shopOrderItemsById = [];
    if ($shopOrders) {
        $soIds = array_map(static fn(array $r): int => (int) $r['id'], $shopOrders);
        $ph = implode(',', array_fill(0, count($soIds), '?'));
        foreach (db_all("SELECT * FROM shop_order_items WHERE shop_order_id IN ({$ph}) ORDER BY id ASC", $soIds) as $sli) {
            $shopOrderItemsById[(int) $sli['shop_order_id']][] = $sli;
        }
    }
    render_public('orders.php', [
        'orders'             => $orders,
        'shopOrders'         => $shopOrders,
        'shopOrderItemsById' => $shopOrderItemsById,
        'pageTitle'          => 'My Orders',
        'activeNav'          => 'account',
        'robotsNoIndex'      => true,
    ]);
}

if ($route === '/wishlist') {
    header('X-Robots-Tag: noindex, nofollow');
    $user = require_login('/wishlist');
    $filters = browse_extract_filters($_GET);
    $res = browse_query_items(['mode' => 'wishlist', 'user_id' => (int) $user['id']], $filters);
    preload_items_card_meta($res['items']);
    $shopWishlistProducts = db_all(
        'SELECT p.* FROM product_wishlist pw JOIN products p ON p.id = pw.product_id WHERE pw.user_id = ? AND p.is_active = 1 ORDER BY pw.id DESC',
        [(int) $user['id']]
    );
    render_public('wishlist.php', [
        'wishlistItems'        => $res['items'],
        'shopWishlistProducts' => $shopWishlistProducts,
        'filters'              => $filters,
        'page'          => $res['page'],
        'totalPages'    => $res['totalPages'],
        'totalItems'    => $res['total'],
        'pageTitle'     => t('nav_wishlist'),
        'activeNav'     => 'wishlist',
        'robotsNoIndex' => true,
    ]);
}

if ($route === '/notifications') {
    header('X-Robots-Tag: noindex, nofollow');
    $user = require_login('/notifications');
    $uid = (int) $user['id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        if ($act === 'mark_all_read') {
            db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
            flash('success', current_lang() === 'bn' ? 'সব বিজ্ঞপ্তি পঠিত হয়েছে।' : 'All notifications marked as read.');
        } elseif ($act === 'mark_read') {
            $nid = (int) ($_POST['id'] ?? 0);
            if ($nid > 0) {
                db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$nid, $uid]);
            }
        }
        redirect('/notifications');
    }
    $notifications = db_all(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 60',
        [$uid]
    );
    render_public('notifications.php', ['notifications' => $notifications, 'pageTitle' => t('nav_notifications'), 'activeNav' => 'notifications', 'robotsNoIndex' => true]);
}

// ----------------------------------------------------------------------------
// Manual Purchase Checkout: /checkout/{slug}
// ----------------------------------------------------------------------------
if (preg_match('#^/checkout/([^/]+)$#', $route, $m)) {
    header('X-Robots-Tag: noindex, nofollow');
    $slug = rawurldecode($m[1]);
    $user = require_login('/checkout/' . rawurlencode($slug));
    $item = db_one('SELECT * FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$item) {
        redirect('/pdf');
    }
    if ((bool) $item['is_free'] || (float) $item['price'] <= 0 || user_owns_item((int) $user['id'], (int) $item['id'])) {
        redirect('/reader/' . rawurlencode($item['slug']));
    }
    if (user_pending_order((int) $user['id'], (int) $item['id'])) {
        flash('warning', current_lang() === 'bn' ? 'এই বইয়ের জন্য আপনার একটি অর্ডার যাচাইয়ের অপেক্ষায় আছে।' : 'You already have a pending order for this PDF.');
        redirect('/orders');
    }

    $error = null;
    $appliedCoupon = null;
    $basePrice = (float) $item['price'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $gwDriver = payment_gateway_driver();
        $useAutoGw = $gwDriver->isAutomatic();

        $method = $_POST['payment_method'] ?? 'bkash';
        if (!in_array($method, ['bkash', 'nagad', 'rocket'], true)) {
            $method = 'bkash';
        }
        $sender = trim((string) ($_POST['sender_number'] ?? ''));
        $trxId = normalize_transaction_id((string) ($_POST['transaction_id'] ?? ''));
        $couponCode = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
        $agreeLegal = ($_POST['agree_legal'] ?? '') === '1';

        $finalAmount = $basePrice;
        $discountAmount = 0.0;
        $couponId = null;
        $validCouponCode = null;

        if ($couponCode !== '') {
            $chk = validate_coupon_for_checkout($couponCode, (int) $user['id'], (int) $item['id'], $basePrice);
            if (!$chk['ok']) {
                $error = $chk['message'];
            } else {
                $appliedCoupon = $chk;
                $finalAmount = (float) $chk['final_amount'];
                $discountAmount = (float) $chk['discount_amount'];
                $couponId = (int) $chk['coupon_id'];
                $validCouponCode = (string) $chk['code'];
            }
        }

        $isZeroAutoApprove = $finalAmount <= 0;
        $uploadedProof = null;
        if ($error === null && !$isZeroAutoApprove && !$useAutoGw) {
            try {
                $uploadedProof = save_payment_screenshot_upload('payment_screenshot');
            } catch (Throwable $upEx) {
                $error = $upEx->getMessage();
            }
        }

        if ($error === null && !$agreeLegal) {
            $error = current_lang() === 'bn'
                ? 'অর্ডার করতে শর্তাবলি, গোপনীয়তা, রিফান্ড ও কপিরাইট নীতিতে সম্মত হতে হবে।'
                : 'You must agree to the Terms, Privacy, Refund Policy, and Copyright Notice to place an order.';
        } elseif ($error === null && !$isZeroAutoApprove && !$useAutoGw && ($sender === '' || $trxId === '' || str_starts_with($trxId, 'COUPON'))) {
            $error = current_lang() === 'bn'
                ? 'সঠিক প্রেরক নম্বর এবং পেমেন্ট Transaction ID অবশ্যই দিতে হবে।'
                : 'Valid sender number and payment Transaction ID are required.';
        } elseif ($error === null) {
            $dbMethod = $isZeroAutoApprove ? 'free' : ($useAutoGw ? $gwDriver->getCode() : $method);
            $dbGwDrv  = (!$isZeroAutoApprove && $useAutoGw) ? $gwDriver->getCode() : null;
            $dbSender = ($isZeroAutoApprove || $useAutoGw) ? null : mb_substr($sender, 0, 40);
            $dbTrx    = ($isZeroAutoApprove || $useAutoGw) ? null : substr($trxId, 0, 80);
            $orderStatus = $isZeroAutoApprove ? 'approved' : 'pending';
            $orderCode = 'BX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $orderId = 0;

            db()->beginTransaction();
            try {
                if ($dbTrx !== null) {
                    $dupTrx = db_one(
                        'SELECT id FROM orders WHERE payment_method = ? AND transaction_id = ? LIMIT 1',
                        [$dbMethod, $dbTrx]
                    );
                    if ($dupTrx) {
                        db()->rollBack();
                        $error = current_lang() === 'bn'
                            ? 'এই Transaction ID-টি ইতিমধ্যে আরেকটি অর্ডারে ব্যবহৃত হয়েছে। অনুগ্রহ করে সঠিক TrxID দিন বা সাপোর্টে যোগাযোগ করুন।'
                            : 'This Transaction ID has already been used for another order. Please double-check or contact support.';
                    }
                }

                if ($error === null && $couponId !== null) {
                    $cRow = db_one('SELECT per_user_limit FROM coupons WHERE id = ? LIMIT 1 FOR UPDATE', [$couponId]);
                    $perUser = max(1, (int) ($cRow['per_user_limit'] ?? 1));
                    $uUsed = (int) (db_one(
                        "SELECT COUNT(*) AS c FROM orders WHERE user_id = ? AND coupon_id = ? AND status NOT IN ('rejected', 'cancelled', 'expired')",
                        [(int) $user['id'], $couponId]
                    )['c'] ?? 0);
                    if ($uUsed >= $perUser) {
                        db()->rollBack();
                        $error = current_lang() === 'bn'
                            ? 'আপনি ইতিমধ্যে এই কুপনটি সর্বোচ্চ সংখ্যকবার ব্যবহার করেছেন।'
                            : 'You have already reached the per-user limit for this coupon.';
                    } else {
                        $affected = db_exec(
                            'UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= NOW()) AND (usage_limit IS NULL OR used_count < usage_limit)',
                            [$couponId]
                        );
                        if ($affected !== 1) {
                            db()->rollBack();
                            $error = current_lang() === 'bn'
                                ? 'দুঃখিত, এই কুপনের ব্যবহারের সীমা পূর্ণ হয়েছে বা মেয়াদ শেষ হয়েছে।'
                                : 'Sorry, this coupon usage limit has just been reached or expired.';
                        }
                    }
                }

                if ($error === null) {
                    db_exec(
                        'INSERT INTO orders (order_code, user_id, item_id, original_amount, coupon_id, coupon_code, discount_amount, amount, payment_method, gateway_driver, sender_number, transaction_id, payment_screenshot, status, reviewed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $orderCode,
                            (int) $user['id'],
                            (int) $item['id'],
                            $basePrice,
                            $couponId,
                            $validCouponCode,
                            $discountAmount,
                            $finalAmount,
                            $dbMethod,
                            $dbGwDrv,
                            $dbSender,
                            $dbTrx,
                            $uploadedProof,
                            $orderStatus,
                            $isZeroAutoApprove ? date('Y-m-d H:i:s') : null,
                        ]
                    );
                    $orderId = (int) db()->lastInsertId();
                    if ($isZeroAutoApprove) {
                        db_exec(
                            'INSERT INTO library (user_id, item_id, order_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)',
                            [(int) $user['id'], (int) $item['id'], $orderId]
                        );
                    }
                    db()->commit();
                }
            } catch (PDOException $pdoEx) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                if ((string) $pdoEx->getCode() === '23000') {
                    $error = current_lang() === 'bn'
                        ? 'এই Transaction ID-টি ইতিমধ্যে আরেকটি অর্ডারে ব্যবহৃত হয়েছে।'
                        : 'This Transaction ID has already been submitted on another order.';
                } else {
                    throw $pdoEx;
                }
            } catch (Throwable $ex) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $ex;
            }

            if ($error === null) {
                if ($isZeroAutoApprove) {
                    notify_order_status_change($orderId, 'approved');
                    flash('success', current_lang() === 'bn' ? '১০০% ছাড়ে অর্ডার স্বয়ংক্রিয়ভাবে অনুমোদিত হয়েছে! বইটি আপনার লাইব্রেরিতে যুক্ত হয়েছে।' : '100% discount applied — Order auto-approved & unlocked in My Library!');
                    redirect('/library');
                }

                if ($useAutoGw) {
                    $gwRes = $gwDriver->create(['id' => $orderId, 'order_code' => $orderCode, 'amount' => $finalAmount], $item, $user);
                    if (!empty($gwRes['gateway_ref'])) {
                        db_exec('UPDATE orders SET gateway_ref = ? WHERE id = ?', [$gwRes['gateway_ref'], $orderId]);
                    }
                    if (!empty($gwRes['ok']) && !empty($gwRes['redirect_url'])) {
                        header('Location: ' . $gwRes['redirect_url']);
                        exit;
                    }
                    flash('danger', (string) ($gwRes['message'] ?? 'Payment gateway initialization failed.'));
                    redirect('/orders');
                }

                $invoiceAbs = BASE_URL . '/orders/' . rawurlencode($orderCode) . '/invoice';
                if (!empty($user['email'])) {
                    send_bookexa_template_mail(
                        (string) $user['email'],
                        'Order Received #' . $orderCode . ' — Bookexa',
                        'Order Received #' . $orderCode,
                        '<p>Hello <strong>' . e($user['name']) . '</strong>,</p>'
                        . '<p>We received your order for <strong>' . e($item['title_en']) . '</strong> (' . e(format_bdt($finalAmount)) . ').</p>'
                        . '<p>Your payment details are pending admin verification. <a href="' . e($invoiceAbs) . '">View Invoice #' . e($orderCode) . '</a></p>',
                        BASE_URL . '/orders',
                        'View My Orders'
                    );
                }

                flash('success', current_lang() === 'bn' ? 'আপনার অর্ডার জমা হয়েছে! অ্যাডমিন যাচাই করলে বইটি My Library-তে আনলক হবে।' : 'Order submitted! Once Admin approves, the PDF unlocks in My Library.');
                redirect('/orders');
            }
        }
    }

    render_public('checkout.php', ['item' => $item, 'error' => $error, 'appliedCoupon' => $appliedCoupon, 'pageTitle' => t('buy_now_btn'), 'activeNav' => 'pdfs', 'robotsNoIndex' => true]);
}

// ----------------------------------------------------------------------------
// Protected In-Browser PDF Reader: /reader/{slug}
// ----------------------------------------------------------------------------
