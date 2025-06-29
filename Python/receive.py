# 适用于python3.10+以上版本的MQTT消息订阅接收样例

import random
from paho.mqtt import client as mqtt_client


def connect_mqtt(broker, port, username, password) -> mqtt_client:
    def on_connect(client, userdata, flags, rc, properties=None):
        if rc == 0:
            print("Connected to MQTT Broker!")
        else:
            print("Failed to connect, return code %d\n", rc)

    # 使用 CallbackAPIVersion.VERSION2
    client = mqtt_client.Client(callback_api_version=mqtt_client.CallbackAPIVersion.VERSION2,
                                client_id=f'python-mqtt-{random.randint(0, 100)}')
    
    client.tls_set()  # 使用默认 TLS 配置（适用于 Python 3.10+）
    client.tls_insecure_set(True)  # 不校验证书（仅限测试）
    client.username_pw_set(username, password)
    client.on_connect = on_connect
    client.connect(broker, port)
    return client


def subscribe(client: mqtt_client, topic):
    def on_message(client, userdata, msg, properties=None):
        print(f"Received `{msg.payload.decode()}` from `{msg.topic}` topic")

    client.subscribe(topic, qos=2)  # 显式指定 qos=2 以接受任何 QoS 消息
    client.on_message = on_message


def run(broker, port, topic, username, password):
    client = connect_mqtt(broker, port, username, password)
    subscribe(client, topic)
    try:
        client.loop_forever()
    except KeyboardInterrupt:
        print("Stopping MQTT client...")
        client.disconnect()

#请在这里填入相关信息
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
    run(broker, port, topic, username, password)