import random
import ssl  # 如果后续有需要处理 SSL 相关配置可能用到
import paho.mqtt.client as mqtt_client  # 添加此行以定义 mqtt_client


def connect_mqtt(broker, port, username, password, ssl_enable='no') -> mqtt_client:
    def on_connect(client, userdata, flags, rc, properties=None):
        if rc == 0:
            print("Connected to MQTT Broker!")
        else:
            print("Failed to connect, return code %d\n", rc)

    # 使用 CallbackAPIVersion.VERSION2
    client = mqtt_client.Client(callback_api_version=mqtt_client.CallbackAPIVersion.VERSION2,
                                client_id=f'python-mqtt-{random.randint(0, 100)}')
    
    client.username_pw_set(username, password)
    client.on_connect = on_connect

    # 配置SSL
    if ssl_enable.lower() == 'yes':
        # 启用SSL/TLS
        client.tls_set()  # 可根据需要添加证书路径等参数
        client.tls_insecure_set(True)  # 如果使用自签名证书可跳过验证
    # 如果 ssl_enable 为 'no'，则不设置 SSL 相关参数

    client.connect(broker, port)
    return client

# 新增 subscribe 函数定义
def subscribe(client, topic):
    def on_message(client, userdata, msg):
        if msg.topic == topic:
            print(f"Received message from topic '{msg.topic}': {msg.payload.decode()}")

    client.subscribe(topic)
    client.on_message = on_message

def run(broker, port, topic, username, password, ssl_enable='no'):
    client = connect_mqtt(broker, port, username, password, ssl_enable)
    subscribe(client, topic)
    try:
        client.loop_forever()
    except KeyboardInterrupt:
        print("Stopping MQTT client...")
        client.disconnect()

# 请在这里填入相关信息
if __name__ == '__main__':
    # mqtt服务器地址
    broker = ''
    # mqtt服务器端口(通常为1883)
    port = 
    # 订阅的topic
    topic = ""
    # 用户名
    username = ''
    # 密码
    password = ''
    # 是否启用SSL (填写 'yes' 或 'no')
    ssl_enable = ''

    run(broker, port, topic, username, password, ssl_enable)