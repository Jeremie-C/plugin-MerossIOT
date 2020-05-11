#!/usr/bin/env python3
import os
import sys
import time
import argparse
import logging
import signal
import threading
import requests
import json
import socketserver

from datetime import datetime
from meross_iot.manager import MerossManager
from meross_iot.meross_event import MerossEventType
from meross_iot.cloud.devices.light_bulbs import GenericBulb
from meross_iot.cloud.devices.power_plugs import GenericPlug
from meross_iot.cloud.devices.door_openers import GenericGarageDoorOpener
from meross_iot.cloud.devices.humidifier import GenericHumidifier, SprayMode
from meross_iot.cloud.devices.hubs import GenericHub
from meross_iot.cloud.devices.subdevices.thermostats import ValveSubDevice, ThermostatV3Mode

from meross_iot.cloud.devices.light_bulbs import MODE_RGB, MODE_LUMINANCE, MODE_TEMPERATURE, to_rgb

# Envoi vers Jeedom ------------------------------------------------------------
class JeedomCallback:
    def __init__(self, apikey, url):
        self.apikey = apikey
        self.url = url
        self.messages = []
        self._stop = False
        self.t = threading.Thread(target=self.run)
        self.t.setDaemon(True)
        self.t.start()

    def stop(self):
        self._stop = True

    def send(self, message):
        self.messages.append(message)
        logging.debug('Nouveau message : {}'.format(message))
        logging.debug('Nombre de messages : {}'.format(len(self.messages)))

    def send_now(self, message):
        return self._request(message)

    def run(self):
        while not self._stop:
            while self.messages:
                m = self.messages.pop(0)
                try:
                    self._request(m)
                except Exception as error:
                    logging.error('Erreur envoie requête à jeedom {}'.format(error))
            time.sleep(0.5)

    def _request(self, m):
        response = None
        logging.debug('Envoie à jeedom :  {}'.format(m))
        r = requests.post('{}?apikey={}'.format(self.url, self.apikey), data=json.dumps(m), verify=False)
        if r.status_code != 200:
            logging.error('Erreur envoie requête à jeedom, return code {} - {}'.format(r.status_code, r.reason))
        else:
            response = r.json()
            logging.debug('Réponse de jeedom :  {}'.format(response))
        return response

    def test(self):
        logging.debug('Envoi un test à jeedom')
        r = self.send_now({'action': 'test'})
        if not r or not r.get('success'):
            logging.error('Erreur envoi à jeedom')
            return False
        return True

    def event_handler(self, eventobj):
        logging.debug("Event : {}".format(eventobj.event_type))
        if eventobj.event_type == MerossEventType.DEVICE_SWITCH_STATUS:
            self.send({'action': 'switch', 'uuid':eventobj.device.uuid, 'channel':eventobj.channel_id, 'status':int(eventobj.switch_state)})
        elif eventobj.event_type == MerossEventType.DEVICE_ONLINE_STATUS:
            self.send({'action': 'online', 'uuid':eventobj.device.uuid, 'status':eventobj.status})
        elif eventobj.event_type == MerossEventType.DEVICE_BULB_SWITCH_STATE:
            self.send({'action': 'switch', 'uuid':eventobj.device.uuid, 'channel':eventobj.channel, 'status':int(eventobj.is_on)})
        elif eventobj.event_type == MerossEventType.DEVICE_BULB_STATE:
            self.send({'action': 'bulb', 'uuid':eventobj.device.uuid, 'channel':eventobj.channel, 'status':eventobj.light_state})
        elif eventobj.event_type == MerossEventType.GARAGE_DOOR_STATUS:
            self.send({'action': 'door', 'uuid':eventobj.device.uuid, 'channel':eventobj.channel, 'status':eventobj.door_state})
        #HUMIDIFIER
        elif eventobj.event_type == MerossEventType.HUMIDIFIER_LIGHT_EVENT:
            self.send({'action': 'hlight', 'uuid':eventobj.device.uuid, 'channel':eventobj.channel, 'status':int(eventobj.is_on), 'rgb':int(to_rgb(eventobj.rgb)), 'luminance':eventobj.luminance})
        elif eventobj.event_type == MerossEventType.HUMIDIFIER_SPRY_EVENT:
            self.send({'action': 'hspray', 'uuid':eventobj.device.uuid, 'channel':eventobj.channel, 'status':int(eventobj.spry_mode.value)})
        #ADDITIONS
        elif eventobj.event_type == MerossEventType.CLIENT_CONNECTION:
            self.send({'action': 'connect', 'status':eventobj.status.value})
        elif eventobj.event_type == MerossEventType.DEVICE_BIND:
            self.send({'action': 'bind', 'uuid':eventobj.device.uuid, 'data':eventobj.bind_data})
        elif eventobj.event_type == MerossEventType.DEVICE_UNBIND:
            self.send({'action': 'unbind', 'uuid':eventobj.device.uuid})        
        #elif eventobj.event_type == MerossEventType.THERMOSTAT_MODE_CHANGE:
        #elif eventobj.event_type == MerossEventType.THERMOSTAT_TEMPERATURE_CHANGE:

# Reception de Jeedom ----------------------------------------------------------
class JeedomHandler(socketserver.BaseRequestHandler):
    def handle(self):
        # self.request is the TCP socket connected to the client
        data = self.request.recv(1024)
        logging.debug("Message received in socket")
        message = json.loads(data.decode())
        lmessage = dict(message)
        del lmessage['apikey']
        logging.debug(lmessage)
        if message.get('apikey') != _apikey:
            logging.error("Invalid apikey from socket : {}".format(data))
            return
        response = {'result': None, 'success': True}
        action = message.get('action')
        args = message.get('args')
        if hasattr(self, action):
            func = getattr(self, action)
            response['result'] = func
            if callable(response['result']):
                response['result'] = response['result'](*args)
        logging.debug(response)
        self.request.sendall(json.dumps(response).encode())

    def setOn(self, uuid, channel=0):
        device = mm.get_device_by_uuid(uuid)
        if device is not None:
            if str(device.__class__.__name__) == 'GenericGarageDoorOpener':
                res = device.close_door(channel=int(channel))
            elif str(device.__class__.__name__) == 'GenericHumidifier':
                res = device.turn_on_light()
            else:
                res = device.turn_on_channel(int(channel))
            return res
        else:
            return 'Unknow device'

    def setOff(self, uuid, channel=0):
        device = mm.get_device_by_uuid(uuid)
        if device is not None:
            if str(device.__class__.__name__) == 'GenericGarageDoorOpener':
                res = device.open_door(channel=int(channel))
            elif str(device.__class__.__name__) == 'GenericHumidifier':
                res = device.turn_off_light()
            else:
                res = device.turn_off_channel(int(channel))
            return res
        else:
            return 'Unknow device'

    def setLumi(self, uuid, lumi_int):
        device = mm.get_device_by_uuid(uuid)
        if device is not None:
            if str(device.__class__.__name__) == 'GenericHumidifier':
                res = device.configure_light(onoff=1, luminance=lumi_int)
            else:
                res = device.set_light_color(luminance=lumi_int)
            return res
        else:
            return 'Unknow device'

    def setTemp(self, uuid, temp_int, lumi=-1):
        device = mm.get_device_by_uuid(uuid)
        if device is not None:
            res = device.set_light_color(temperature=temp_int, luminance=lumi)
            return res
        else:
            return 'Unknow device'

    def setRGB(self, uuid, rgb_int, lumi=-1):
        device = mm.get_device_by_uuid(uuid)
        if device is not None:
            if str(device.__class__.__name__) == 'GenericHumidifier':
                res = device.configure_light(onoff=1, rgb=int(rgb_int), luminance=lumi)
            else:
                res = device.set_light_color(rgb=int(rgb_int), luminance=lumi)
            return res
        else:
            return 'Unknow device'

    def setSpray(self, uuid, smode=0):
        device = mm.get_device_by_uuid(uuid)
        if device is not None:
            if smode == '1':
                res = device.set_spray_mode(spray_mode=SprayMode.CONTINUOUS)
            elif smode == '2':
                res = device.set_spray_mode(spray_mode=SprayMode.INTERMITTENT)
            else:
                res = device.set_spray_mode(spray_mode=SprayMode.OFF)
            return res
        else:
            return 'Unknow device'

    def syncOneMeross(self, device):
        d = dict({
            'name': device.name,
            'uuid': device.uuid,
            'famille': str(device.__class__.__name__),
            'online': device.online,
            'type': device.type,
            'ip': '',
            'mac': ''
        })
        # Hors ligne : fin
        if not device.online:
            return d
        # En Ligne Seulement
        data = device.get_sys_data()
        d['values'] = {}
        # Nom Canaux
        onoff = [device.name]
        for x in device._channels:
            try:
                onoff.append(x['devName'])
            except:
                pass
        d['onoff'] = onoff
        # Valeur Canaux
        switch = []
        try:
            switch = [data['all']['control']['toggle']['onoff']]
        except:
            try:
                digest = data['all']['digest']['togglex']
                switch = [x['onoff'] for x in digest]
            except:
                try:
                    switch = [device.get_light_state()['onoff']]
                except:
                    pass
        d['values']['switch'] = switch
        # IP
        try:
            d['ip'] = data['all']['system']['firmware']['innerIp']
        except:
            pass
        # MAC
        try:
            d['mac'] = data['all']['system']['hardware']['macAddress']
        except:
            pass
        # Puissance
        if device.supports_electricity_reading():
            d['elec'] = True
            electricity = device.get_electricity()
            d['values']['power'] = float(electricity['power'] / 1000.)
            d['values']['current'] = float(electricity['current'] / 1000.)
            d['values']['voltage'] = float(electricity['voltage'] / 10.)
        else:
            d['elec'] = False
        # Consommation
        if device.supports_consumption_reading():
            d['conso'] = True
            try:
                l_conso = device.get_power_consumption()
            except:
                l_conso = []
            # Recup
            if len(l_conso) > 0:
                d['values']['conso_totale'] = 0
                today = datetime.today().strftime("%Y-%m-%d")
                for c in l_conso:
                    if c['date'] == today:
                        try:
                            d['values']['conso_totale'] = float(c['value'] / 1000.)
                        except:
                            pass
        else:
            d['conso'] = False
        # Lumiere
        if device.supports_light_control():
            d['light'] = True
            digest = data['all']['digest']['light']
            d['lumin'] = device.supports_mode(MODE_LUMINANCE)
            d['tempe'] = device.supports_mode(MODE_TEMPERATURE)
            d['isrgb'] = device.supports_mode(MODE_RGB)
            if d['lumin']:
                d['values']['lumival'] = digest['luminance']
            if d['tempe']:
                d['values']['tempval'] = digest['temperature']
            if d['isrgb']:
                d['values']['rgbval'] = digest['rgb']
            d['values']['capacity'] = digest['capacity']
        else:
            d['light'] = False
            d['lumin'] = False
            d['tempe'] = False
            d['isrgb'] = False
        # HUMIDIFIER
        if d['famille'] == "GenericHumidifier":
            d['spray'] = True
            d['values']['spray'] = device.get_spray_mode().value
        else:
            d['spray'] = False
        # Fini
        return d

    def getMerossConso(self, device):
        d = dict({
            'conso_totale': 0
        })
        try:
            l_conso = device.get_power_consumption()
        except:
            l_conso = []
        # Recup
        if len(l_conso) > 0:
            today = datetime.today().strftime("%Y-%m-%d")
            for c in l_conso:
                if c['date'] == today:
                    try:
                        d['conso_totale'] = float(c['value'] / 1000.)
                    except:
                        pass
        return d

    def syncMeross(self):
        d_devices = {}
        devices = mm.get_supported_devices()
        for num in range(len(devices)):
            device = devices[num]
            d = self.syncOneMeross(device)
            uuid = device.uuid
            d_devices[uuid] = d
        return d_devices

    def syncDevice(self, uuid):
        d_device = {}
        device = mm.get_device_by_uuid(uuid)
        d = self.syncOneMeross(device)
        d_device = d
        return d_device

    def syncMerossConso(self):
        d_devices = {}
        devices = mm.get_supported_devices()
        for num in range(len(devices)):
            device = devices[num]
            if device.online and device.supports_consumption_reading():
                d = self.getMerossConso(device)
                uuid = device.uuid
                d_devices[uuid] = d
        return d_devices

# Les fonctions du daemon ------------------------------------------------------
def convert_log_level(level='error'):
    LEVELS = {'debug': logging.DEBUG,
              'info': logging.INFO,
              'notice': logging.WARNING,
              'warning': logging.WARNING,
              'error': logging.ERROR,
              'critical': logging.CRITICAL,
              'none': logging.NOTSET}
    return LEVELS.get(level, logging.NOTSET)

def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()

def shutdown():
    logging.debug("Arrêt")
    logging.debug("Arrêt Meross Manager")
    mm.stop()
    logging.debug("Stop callback server")
    jc.stop()
    logging.debug("Arrêt du démon local")
    server.shutdown()
    updateElec()
    logging.debug("Effacement fichier PID " + str(_pidfile))
    if os.path.exists(_pidfile):
        os.remove(_pidfile)
    logging.debug("Effacement fichier socket " + str(_sockfile))
    if os.path.exists(_sockfile):
        os.remove(_sockfile)
    logging.debug("Exit 0")

# ----------------------------------------------------------------------------
def syncOneElectricity(device):
    d = dict({
        'power': 0,
        'current': 0,
        'voltage':0
    })
    # Puissance
    if device.supports_electricity_reading():
        try:
            electricity = device.get_electricity()
            d['power'] = float(electricity['power'] / 1000.)
            d['current'] = float(electricity['current'] / 1000.)
            d['voltage'] = float(electricity['voltage'] / 10.)
        except:
            pass
    # Fini
    return d

def UpdateAllElectricity(interval):
    stopped = threading.Event()
    def loop():
        while not stopped.wait(interval):
            e_devices = {}
            try:
                devices = mm.get_supported_devices()
                for num in range(len(devices)):
                    device = devices[num]
                    if device.online:
                        d = syncOneElectricity(device)
                        uuid = device.uuid
                        e_devices[uuid] = d
                # Fin du for
                logging.info('Send Electricity')
                #jc.sendElectricity(e_devices)
                jc.send({'action': 'electricity', 'values':e_devices})
            except:
                pass
    # fin de loop
    threading.Thread(target=loop).start()
    return stopped.set

# ----------------------------------------------------------------------------
parser = argparse.ArgumentParser()
parser.add_argument('--muser', help='Compte Meross', default='')
parser.add_argument('--mpswd', help='Mot de passe Meross', default='')
parser.add_argument('--mupdp', help='Fréquence actualisation puissance', type=int, default=30)
parser.add_argument('--callback', help='Jeedom callback', default='http://localhost')
parser.add_argument('--apikey', help='API Key', default='nokey')
parser.add_argument('--loglevel', help='LOG Level', default='error')
parser.add_argument('--pidfile', help='PID File', default='/tmp/MerossIOTd.pid')
parser.add_argument('--socket', help='Daemon socket', default='/tmp/MerossIOTd.sock')
args = parser.parse_args()

FORMAT = '[%(asctime)-15s][%(levelname)s][%(name)s](%(threadName)s) : %(message)s'
logging.basicConfig(level=convert_log_level(args.loglevel), format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")
urllib3_logger = logging.getLogger('urllib3')
urllib3_logger.setLevel(logging.CRITICAL)

logging.info('Start MerossIOTd')
logging.info('Log level : {}'.format(args.loglevel))
logging.info('Socket : {}'.format(args.socket))
logging.info('PID file : {}'.format(args.pidfile))
logging.info('Apikey : {}'.format(args.apikey))
logging.info('Update Power : {}'.format(args.mupdp))
logging.info('Callback : {}'.format(args.callback))
logging.info('Python version : {}'.format(sys.version))

_pidfile = args.pidfile
_sockfile = args.socket
_apikey = args.apikey

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

pid = str(os.getpid())
logging.debug("Ecriture du PID " + pid + " dans " + str(args.pidfile))
with open(args.pidfile, 'w') as fp:
    fp.write("%s\n" % pid)

jc = JeedomCallback(args.apikey, args.callback)
if not jc.test():
    sys.exit()

if os.path.exists(args.socket):
    os.unlink(args.socket)

server = socketserver.UnixStreamServer(args.socket, JeedomHandler)
logging.info('Démarrage Meross Manager')
# Initiates the Meross Cloud Manager. This is in charge of handling the communication with the remote endpoint
mm = MerossManager.from_email_and_password(args.muser, args.mpswd)
# Register event handlers for the manager...
mm.register_event_handler(jc.event_handler)
mm.start()
# Thread for JeedomHandler
t = threading.Thread(target=server.serve_forever)
t.start()
# Update Conso Power 
updateElec = UpdateAllElectricity(float(args.mupdp))
