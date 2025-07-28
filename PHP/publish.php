<!DOCTYPE html>
<html>
<head>
    <title>MQTT 5.0 发布器</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
        }
        .button {
            padding: 15px 30px;
            margin: 10px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            max-width: 300px;
        }
        .toggle-button {
            background-color: #ff9800;
            color: white;
        }
        .status {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
        
        /* 响应式设计 */
        @media (max-width: 600px) {
            body {
                margin: 10px auto;
                padding: 10px;
            }
            .button {
                padding: 12px 20px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <h1>MQTT 5.0 消息发布器</h1>
    
    <form method="POST">
        <button type="submit" name="action" value="toggle" class="button toggle-button">
            <?php 
                // 获取当前状态，默认为off
                $currentState = isset($_COOKIE['mqtt_state']) ? $_COOKIE['mqtt_state'] : 'off';
                echo $currentState === 'on' ? '发送 OFF 消息' : '发送 ON 消息';
            ?>
        </button>
    </form>

    <?php
    // ==================== MQTT 配置参数 ====================
    // Broker 地址和端口
    $host = '';
    $port = 1883;
    
    // 发布主题和 QoS 级别
    $topic = '';
    $qos = 0;
    
    // 认证信息（如不需要请留空）
    $username = 'your_username';
    $password = 'your_password';
    
    // SSL/TLS 配置
    $useSSL = false; // 设置为 true 以启用 SSL/TLS 连接

    // 客户端 ID（唯一标识符）
    $clientId = 'php_publisher_' . uniqid();
    // ==================== 配置结束 ====================
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // 获取当前状态并切换
        $currentState = isset($_COOKIE['mqtt_state']) ? $_COOKIE['mqtt_state'] : 'off';
        $message = $currentState === 'on' ? 'off' : 'on';
        
        try {
            // 实现MQTT发布功能
            $result = publishMqttMessage($host, $port, $clientId, $username, $password, $useSSL, $topic, $message, $qos);
            
            if ($result) {
                // 更新状态cookie
                setcookie('mqtt_state', $message, time() + 3600, '/');
                echo "<div class='status success'>成功发送 '$message' 消息到主题 '$topic'</div>";
            } else {
                echo "<div class='status error'>发送消息失败</div>";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>错误: " . $e->getMessage() . "</div>";
        }
    }
    
    // 使用原生PHP实现MQTT发布功能
    function publishMqttMessage($host, $port, $clientId, $username, $password, $useSSL, $topic, $message, $qos) {
        // 构建MQTT CONNECT包
        function buildConnectPacket($clientId, $username = '', $password = '') {
            $protocolName = "MQTT";
            $protocolLevel = 5; // MQTT 5.0
            
            $connectFlags = 0;
            if (!empty($username)) $connectFlags |= 0x80;
            if (!empty($password)) $connectFlags |= 0x40;
            $connectFlags |= 0x02; // Clean session
            
            $keepAlive = 60;
            
            // 固定头部
            $buffer = chr(0x10); // CONNECT控制包类型
            
            // 可变头部和载荷
            $payload = '';
            
            // 协议名称长度和名称
            $payload .= pack('n', strlen($protocolName)) . $protocolName;
            
            // 协议级别
            $payload .= chr($protocolLevel);
            
            // 连接标志
            $payload .= chr($connectFlags);
            
            // 保持连接时间
            $payload .= pack('n', $keepAlive);
            
            // 属性长度 (MQTT 5.0)
            $payload .= chr(0x00); // 无属性
            
            // Client ID
            $payload .= pack('n', strlen($clientId)) . $clientId;
            
            // 用户名和密码
            if (!empty($username)) {
                $payload .= pack('n', strlen($username)) . $username;
                if (!empty($password)) {
                    $payload .= pack('n', strlen($password)) . $password;
                }
            }
            
            // 计算剩余长度
            $remainingLength = strlen($payload);
            $remainingLengthBytes = encodeRemainingLength($remainingLength);
            
            // 组合完整包
            $buffer .= $remainingLengthBytes . $payload;
            
            return $buffer;
        }
        
        // 构建MQTT PUBLISH包
        function buildPublishPacket($topic, $message, $qos = 0, $packetId = null) {
            $buffer = chr(0x30 | ($qos << 1)); // PUBLISH控制包类型
            
            // 主题名长度和主题名
            $payload = pack('n', strlen($topic)) . $topic;
            
            // 包标识符 (QoS > 0时需要)
            if ($qos > 0 && $packetId !== null) {
                $payload .= pack('n', $packetId);
            }
            
            // 属性长度 (MQTT 5.0)
            $payload .= chr(0x00); // 无属性
            
            // 消息内容
            $payload .= $message;
            
            // 计算剩余长度
            $remainingLength = strlen($payload);
            $remainingLengthBytes = encodeRemainingLength($remainingLength);
            
            // 组合完整包
            $buffer .= $remainingLengthBytes . $payload;
            
            return $buffer;
        }
        
        // 构建MQTT DISCONNECT包
        function buildDisconnectPacket() {
            return chr(0xE0) . chr(0x00); // DISCONNECT控制包类型和剩余长度
        }
        
        // 编码剩余长度 (MQTT变长编码)
        function encodeRemainingLength($length) {
            $buffer = '';
            do {
                $digit = $length % 128;
                $length = floor($length / 128);
                if ($length > 0) {
                    $digit = ($digit | 0x80);
                }
                $buffer .= chr($digit);
            } while ($length > 0);
            
            return $buffer;
        }
        
        // 创建socket连接
        $scheme = $useSSL ? 'tls://' : 'tcp://';
        $socket = stream_socket_client("{$scheme}{$host}:{$port}", $errno, $errstr, 30);
        
        if (!$socket) {
            throw new Exception("Failed to connect to MQTT broker: $errstr ($errno)");
        }
        
        // 设置流为阻塞模式
        stream_set_blocking($socket, true);
        
        // 发送CONNECT包
        $connectPacket = buildConnectPacket($clientId, $username, $password);
        fwrite($socket, $connectPacket);
        
        // 读取CONNACK响应
        $byte1 = fread($socket, 1);
        if ($byte1 === false || ord($byte1) != 0x20) {
            fclose($socket);
            throw new Exception("Invalid CONNACK packet received");
        }
        
        // 读取剩余长度
        $multiplier = 1;
        $value = 0;
        do {
            $digit = ord(fread($socket, 1));
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
        } while (($digit & 128) != 0);
        
        // 读取CONNACK可变头部
        $connackData = fread($socket, $value);
        if (strlen($connackData) < 2) {
            fclose($socket);
            throw new Exception("Incomplete CONNACK packet received");
        }
        
        // 检查连接返回码 (CONNACK variable header的第二个字节)
        $returnCode = ord(substr($connackData, 1, 1));
        if ($returnCode != 0) {
            fclose($socket);
            // 提供更详细的错误信息
            $errorMessages = [
                0 => "Connection Accepted",
                1 => "Unacceptable Protocol Version",
                2 => "Identifier Rejected",
                3 => "Server Unavailable",
                4 => "Bad User Name or Password",
                5 => "Not Authorized",
                149 => "Authentication failed or other server error"
            ];
            $errorMessage = isset($errorMessages[$returnCode]) ? $errorMessages[$returnCode] : "Unknown error";
            throw new Exception("Connection failed with return code: $returnCode ($errorMessage)");
        }
        
        // 发送PUBLISH包
        $packetId = ($qos > 0) ? rand(1, 65535) : null;
        $publishPacket = buildPublishPacket($topic, $message, $qos, $packetId);
        fwrite($socket, $publishPacket);
        
        // 如果QoS > 0，等待PUBACK
        if ($qos > 0) {
            $byte1 = fread($socket, 1);
            if ($byte1 === false || (ord($byte1) & 0xF0) != 0x40) {
                fclose($socket);
                throw new Exception("Invalid PUBACK packet received");
            }
            
            // 读取剩余长度
            $multiplier = 1;
            $value = 0;
            do {
                $digit = ord(fread($socket, 1));
                $value += ($digit & 127) * $multiplier;
                $multiplier *= 128;
            } while (($digit & 128) != 0);
            
            // 读取PUBACK数据
            fread($socket, $value);
        }
        
        // 发送DISCONNECT包
        $disconnectPacket = buildDisconnectPacket();
        fwrite($socket, $disconnectPacket);
        
        // 关闭连接
        fclose($socket);
        
        return true;
    }
    ?>

    <div style="margin-top: 30px;">
        <h3>配置信息:</h3>
        <ul>
            <li>代理地址: <?php echo $host . ':' . $port; ?></li>
            <li>主题: <?php echo $topic; ?></li>
            <li>服务质量: <?php echo $qos; ?></li>
            <li>客户端ID: <?php echo $clientId; ?></li>
            <li>用户名: <?php echo empty($username) ? '无' : $username; ?></li>
            <li>SSL: <?php echo $useSSL ? '启用' : '禁用'; ?></li>
            <li>当前状态: <?php 
                $currentState = isset($_COOKIE['mqtt_state']) ? $_COOKIE['mqtt_state'] : 'off';
                echo $currentState === 'on' ? 'ON' : 'OFF';
            ?></li>
        </ul>
    </div>
</body>
</html>