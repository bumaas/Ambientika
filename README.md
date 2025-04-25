# Ambientika  <!-- omit in toc -->

Das Ambientika-Modul ermöglicht die Integration von Lüftern der Marke Ambientika, entwickelt von der Firma Südwind, in die Symcon-Softwareumgebung.

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Vorbemerkungen](#1-vorbemerkungen)
	- [Zur Library](#zur-library)
	- [Zur Integration von Geräten](#zur-integration-von-geräten)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Enthaltende Module](#3-enthaltende-module)
- [4. Software-Installation](#4-software-installation)
- [5. Einrichten der Instanzen in Symcon](#5-einrichten-der-instanzen-in-symcon)
- [6. Anhang](#6-anhang)
	- [1. GUID der Module](#1-guid-der-module)
	- [2. Changelog](#2-changelog)


----------
## 1. Vorbemerkungen

### Zur Library
Ambientika Lüfter bieten eine cloudbasierte Lösung für die Raumlüftung und können über die Ambientika-App gesteuert werden. Dieses Modul ermöglicht es, alle in der Ambientika Cloud registrierten Lüfter komfortabel in Symcon zu integrieren und zu verwalten.

Die Kommunikation erfolgt dabei vollständig über die Ambientika Cloud.

----------
### Zur Integration von Geräten

Es werden Instanzen zur Kommunikation mit der Cloud (Cloud IO), einrichten von Geräten in Symcon (Konfigurator) und die eigentlichen Geräte Instanzen bereitgestellt.

Für den Betrieb ist zwingend ein Internetzugang sowie die Zugangsdaten des Accounts der Ambientika Cloud nötig.


## 2. Voraussetzungen

- IPS 8.0 oder höher
- Eingebundene Geräte in der Ambientika App

## 3. Enthaltende Module

Folgende Module beinhaltet das Ambientika Repository:

- __Ambientika Konfigurator__ ([Dokumentation](Ambientika%20Configurator))  
  Konfigurator, welcher alle in der Cloud vorhandenen Geräte anzeigt und zum Erstellen von Geräten-Instanzen anbietet.


- __Ambientika Device__ ([Dokumentation](Ambientika%20Device))  
  Geräte Instanz, welche jeweils ein Gerät in Symcon abbildet.


- __Ambientika Cloud__ ([Dokumentation](Ambientika%20Cloud))  
  IO Instanz zur Kommunikation mit der Cloud..

## 4. Software-Installation

Über den `Module-Store` in Symcon das Modul `Ambientika` hinzufügen.  

## 5. Einrichten der Instanzen in Symcon

Details sind direkt in der Dokumentation der jeweiligen Module beschrieben.  

Es wird empfohlen die Einrichtung mit der Konfigurator-Instanz zu starten [Ambientika Configurator](Ambientika%20Configurator/README.md).  

Nach der Installation aus dem Store wird diese Instanz auf Rückfrage automatisch angelegt.

## 6. Anhang

###  1. GUID der Module

| Modul                   | Typ          | Prefix     | GUID                                   |
|-------------------------|--------------|------------|----------------------------------------|
| Ambientika Cloud        | IO           | AMBIENTIKA | {C10C91F4-1843-CBE1-D974-2395A4620106} |
| Ambientika Configurator | Configurator | AMBIENTIKA | {EF9FC7FE-BB28-0322-A4AC-099FDB5C23C0} |
| Ambientika Device       | Device       | AMBIENTIKA | {8CF53BBB-FDB2-7850-79D5-42A14A8649B3} |

### 2. Changelog


Version 1.0 build 7:
- Start der offenen Beta  

