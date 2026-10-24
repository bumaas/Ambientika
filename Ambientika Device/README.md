# Ambientika Device <!-- omit in toc -->

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in Symcon](#4-einrichten-der-instanzen-in-symcon)
    - [Konfigurationsseite (Parameter)](#konfigurationsseite-parameter)
    - [Konfigurationsseite (Status und Bedienung)](#konfigurationsseite-status-und-bedienung)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
    - [Statusvariablen](#statusvariablen)
    - [Profile](#profile)
- [6. Visualisierung](#6-Visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
    - [Statusaktualisierung](#statusaktualisierung)
- [8. Aktionen](#8-aktionen)

## 1. Funktionsumfang

Instanz für die Integration eines Ambientika Lüfters in Symcon.

## 2. Voraussetzungen

- Eingebundene Geräte in der Ambientika App

## 3. Software-Installation

* Dieses Modul ist Bestandteil der [Ambientika-Library](../README.md#4-software-installation).

## 4. Einrichten der Instanzen in Symcon

Unter `Instanz hinzufügen` ist das `Ambientika Device`-Modul unter dem Hersteller `Südwind` aufgeführt.  

Es wird empfohlen diese Instanz über die dazugehörige Instanz des [Ambientika Configurator-Moduls](../Ambientika%20Configurator/README.md) anzulegen.


### Konfigurationsseite (Parameter)

| Name                 | Text                     | Beschreibung                             |
|----------------------|--------------------------|------------------------------------------|
| HouseId              | Haus ID                  | Kennung des Hauses (nur zur Information) |
| SerialNumber         | Seriennummer             | Seriennummer des Gerätes                 |
| RefreshStateInterval | Aktualisierungsintervall | Intervall der Statusaktualisierung       |


### Konfigurationsseite (Status und Bedienung)

Über die Schaltfläche `Aktualisiere Status` kann eine manuelle Statusaktualisierung erfolgen.  

Die Instanz zeigt einen dieser Fehlerstatus und schreibt beim Wechsel eine Meldung ins Log:

| Status | Bedeutung | Was tun |
|--------|-----------|---------|
| 201 Seriennummer nicht gesetzt | Die Instanz weiß nicht, welches Gerät gemeint ist. | Seriennummer eintragen, am einfachsten über den Konfigurator. |
| 202 Anfrage fehlgeschlagen | Die Cloud war nicht erreichbar oder hat die Anfrage abgelehnt. | Status und Debug der Cloud-Instanz prüfen, oft sind es die Zugangsdaten. |
| 203 Gerät meldet sich nicht bei der Cloud | Die Cloud kennt das Gerät, hat aber keinen aktuellen Stand von ihm. Meist ist es ausgeschaltet oder hat kein WLAN. | Stromversorgung und WLAN des Geräts prüfen. Die Instanz wird von selbst wieder aktiv, sobald das Gerät sich meldet. |

## 5. Statusvariablen und Profile

### Statusvariablen

Die Statusvariablen werden beim Anlegen des Gerätes automatisch erzeugt.

### Profile

Die Profile (Ambientika.*) inklusive der Übersetzungen, Maßeinheiten usw. werden automatisch erzeugt.
Statusvariablen, welche Aktionen abbilden und keine Parameter erwarten, erhalten das Profil `Ambientika.ExecuteAction` mit der einzigen Assoziation `Ausführen`.

## 6. Visualisierung

Die direkte Darstellung im WebFront ist möglich; es wird aber empfohlen mit Links zu arbeiten.

## 7. PHP-Befehlsreferenz

### Statusaktualisierung

```php
boolean AMBIENTIKA_RequestState(integer $InstanzID);
```
Fragt den aktuellen Status des Gerätes bei der Cloud ab und aktualisiert die Statusvariablen.

```php
void AMBIENTIKA_ResetFilter(integer $InstanzID);
```
Setzt den Filterstatus des Gerätes zurück.

## 8. Aktionen

Es gibt keine speziellen Aktionen für dieses Modul.
