# Ambientika Cloud <!-- omit in toc -->

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in Symcon](#4-einrichten-der-instanzen-in-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
- [6. Visualisierung](#6-visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
- [8. Aktionen](#8-aktionen)


## 1. Funktionsumfang

Instanz für die Kommunikation mit der Ambientika Cloud.

## 2. Voraussetzungen

- angelegtes Benutzerkonto in der Ambientika App

## 3. Software-Installation

* Dieses Modul ist Bestandteil der [Ambientika Library](../README.md#4-software-installation).

## 4. Einrichten der Instanzen in Symcon

Unter `Instanz hinzufügen` ist das `Ambientika Cloud` -Modul unter dem Hersteller `Südwind` aufgeführt.  
Diese Instanz wird automatisch erzeugt, wenn eine neue Instanz vom [Ambientika Configurator-Modul](../Ambientika%20Configurator/README.md) angelegt wird.

### Konfigurationsseite <!-- omit in toc -->

| Name     | Text         | Beschreibung              |
|----------|--------------|---------------------------|
| Username | Benutzername | Benutzername des Accounts |
| Password | Passwort     | Passwort des Accounts     |


## 5. Statusvariablen und Profile

Dieses Modul erstellt keine Statusvariablen und Profile.

## 6. Visualisierung

Dieses Modul ist nicht für die Visualisierung geeignet.

## 7. PHP-Befehlsreferenz

### Anfrage senden

```php
variant AMBIENTIKA_sendRequest(integer $InstanzID, string $path, string $paramsString);
```
Dient zum Sender direkter Anfragen. 

Beispiel:
```php
AMC_sendRequest(36716, '/device/change-mode', '{"deviceSerialNumber":"6055F997BEB8","operatingMode":"Smart"}'));
```
Dieses Modul stellt keine Instanz-Funktionen bereit.

## 8. Aktionen

Es gibt keine speziellen Aktionen für dieses Modul.