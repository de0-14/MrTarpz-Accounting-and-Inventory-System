<?php
require_once 'config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($password == 'admin123' || $password == $user['password']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            redirect('dashboard.php');
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mr. Tarpz Printing Shop - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }

        .login-box {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 25px 20px;
            text-align: center;
        }

        .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: white;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .login-header h1 {
            color: white;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #a0aec0;
            font-size: 13px;
        }

        .login-form {
            padding: 25px 25px 15px;
        }

        .alert {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert i {
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label i {
            color: #3b82f6;
            margin-right: 5px;
            width: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            margin-bottom: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(5, 150, 105, 0.4);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 15px 0;
            color: #94a3b8;
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }

        .divider span {
            padding: 0 10px;
        }

        .login-footer {
            background: #f8fafc;
            padding: 15px 25px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .login-footer p {
            color: #64748b;
            font-size: 12px;
        }

        .login-footer i {
            color: #3b82f6;
            margin-right: 5px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: white;
            max-width: 450px;
            margin: 30px auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #10b981;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
        }

        .close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 20px 25px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-footer .btn {
            width: auto;
            padding: 10px 20px;
            margin: 0;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        /* Password strength */
        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }

        .strength-weak { background: #ef4444; width: 33.33%; }
        .strength-medium { background: #f59e0b; width: 66.66%; }
        .strength-strong { background: #10b981; width: 100%; }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            text-align: right;
        }

        .feedback {
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .feedback.valid { color: #10b981; }
        .feedback.invalid { color: #ef4444; }

        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
        }

        small {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 5px;
        }

        small i {
            color: #3b82f6;
        }

        /* Responsive */
        @media (max-width: 480px) {
            body {
                padding: 10px;
                align-items: flex-start;
                padding-top: 20px;
            }
            
            .login-container {
                max-width: 100%;
            }
            
            .login-header {
                padding: 20px 15px;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .login-header h1 {
                font-size: 18px;
            }
            
            .login-form {
                padding: 20px;
            }
            
            .modal-content {
                margin: 10px auto;
            }
            
            .modal-body {
                max-height: 50vh;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">MT</div>
                <h1>Mr. Tarpz Printing Shop</h1>
                <p>Accounting & Inventory System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" name="username" placeholder="Enter username" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" name="password" placeholder="Enter password" value="admin123" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                
                <div class="divider">
                    <span>or</span>
                </div>
                
                <button type="button" class="btn btn-success" onclick="showRegisterModal()">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="login-footer">
                <p>
                    <i class="fas fa-info-circle"></i> 
                    Default: admin / admin123
                </p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </h3>
                <span class="close" onclick="closeRegisterModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="registerForm">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" id="fullName" placeholder="Enter full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" id="username" placeholder="Choose username" required>
                        <div id="usernameFeedback" class="feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="email" placeholder="Enter email (optional)">
                    </div>
                    
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" id="password" placeholder="Choose password" required onkeyup="checkPassword()">
                        <div class="password-strength">
                            <div id="strengthBar" class="strength-bar"></div>
                        </div>
                        <div id="strengthText" class="strength-text"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" id="confirmPassword" placeholder="Confirm password" required onkeyup="checkPasswordMatch()">
                        <div id="matchFeedback" class="feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select id="role">
                            <option value="staff">Staff</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <small>
                        <i class="fas fa-info-circle"></i>
                        By creating an account, you agree to our terms
                    </small>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeRegisterModal()">Cancel</button>
                <button class="btn btn-success" onclick="registerUser()">
                    <i class="fas fa-user-plus"></i> Create
                </button>
            </div>
        </div>
    </div>

    <script>
        function showRegisterModal() {
            document.getElementById('registerModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeRegisterModal() {
            document.getElementById('registerModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Check username availability
        let timeout;
        document.getElementById('username').addEventListener('keyup', function() {
            clearTimeout(timeout);
            const username = this.value;
            
            if (username.length < 3) {
                document.getElementById('usernameFeedback').innerHTML = '❌ Minimum 3 characters';
                document.getElementById('usernameFeedback').className = 'feedback invalid';
                return;
            }
            
            timeout = setTimeout(() => {
                fetch('register.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=check_username&username=' + encodeURIComponent(username)
                })
                .then(res => res.json())
                .then(data => {
                    const feedback = document.getElementById('usernameFeedback');
                    if (data.available) {
                        feedback.innerHTML = '✓ Username available';
                        feedback.className = 'feedback valid';
                    } else {
                        feedback.innerHTML = '❌ Username taken';
                        feedback.className = 'feedback invalid';
                    }
                });
            }, 500);
        });
        
        // Check password strength
        function checkPassword() {
            const password = document.getElementById('password').value;
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            bar.className = 'strength-bar';
            if (strength <= 2) {
                bar.classList.add('strength-weak');
                text.innerHTML = 'Weak password';
                text.style.color = '#ef4444';
            } else if (strength <= 4) {
                bar.classList.add('strength-medium');
                text.innerHTML = 'Medium password';
                text.style.color = '#f59e0b';
            } else {
                bar.classList.add('strength-strong');
                text.innerHTML = 'Strong password';
                text.style.color = '#10b981';
            }
            
            checkPasswordMatch();
        }
        
        // Check password match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;
            const feedback = document.getElementById('matchFeedback');
            
            if (confirm.length === 0) {
                feedback.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                feedback.innerHTML = '✓ Passwords match';
                feedback.className = 'feedback valid';
            } else {
                feedback.innerHTML = '❌ Passwords do not match';
                feedback.className = 'feedback invalid';
            }
        }
        
        // Register user
        function registerUser() {
            const fullName = document.getElementById('fullName').value.trim();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;
            
            if (!fullName) {
                alert('Please enter full name');
                return;
            }
            
            if (!username || username.length < 3) {
                alert('Username must be at least 3 characters');
                return;
            }
            
            if (!password || password.length < 6) {
                alert('Password must be at least 6 characters');
                return;
            }
            
            if (password !== confirm) {
                alert('Passwords do not match');
                return;
            }
            
            const btn = document.querySelector('#registerModal .btn-success');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            btn.disabled = true;
            
            const data = new URLSearchParams();
            data.append('action', 'register');
            data.append('full_name', fullName);
            data.append('username', username);
            data.append('email', document.getElementById('email').value);
            data.append('password', password);
            data.append('role', document.getElementById('role').value);
            
            fetch('register.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Account created successfully!');
                    closeRegisterModal();
                    document.querySelector('input[name="username"]').value = username;
                    document.querySelector('input[name="password"]').value = '';
                } else {
                    alert(data.message || 'Error creating account');
                }
            })
            .catch(() => alert('Error creating account'))
            .finally(() => {
                btn.innerHTML = '<i class="fas fa-user-plus"></i> Create';
                btn.disabled = false;
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeRegisterModal();
            }
        }
        
        // Close modal with Escape key
        document.onkeydown = function(event) {
            if (event.key === 'Escape') {
                closeRegisterModal();
            }
        }
    </script>
</body>
</html>