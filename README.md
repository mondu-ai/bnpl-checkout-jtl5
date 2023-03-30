# Plugin

JTL 5 integration plugin for Mondu Payment.

## Installation

1. Download .zip folder from the **main** branch on Github repository
2. Navigate to the JTL Shop administration dashboard
3. Navigate to the Plug-in manager from the sidebar
4. Choose **Upload** tab and upload the downloaded plugin file
5. Navigate to the **Available** tab and install the *Mondu Payments* plugin

## JTL Shop Configuration

**Configure API**

1. Navigate to the JTL Shop administration dashboard
2. Expand **Installed plug-ins** menu item on the left side and choose Mondu Payment
3. Configure the fields:
   * API Sandbox Mode: Select yes to point the plugin to the sandbox environment
   * Fill in API Secret
   * Fill in Webhooks Secret

**Configure Payment Methods**

1. Navigate to the Shipments -> Shipping Methods
2. Select desired Shipping method and click on edit icon
3. Enable Mondu Payment in the **ACCEPTED PAYMENT METHODS** section

## JTL Wawi Configuration

### Add Payment Methods
1. Navigate to the Payments -> Payment Methods in JTL Wawi
2. Add following payment methods:

```
Rechnungskauf - jetzt kaufen, später bezahlen
SEPA-Lastschrift - jetzt kaufen, später per Bankeinzug bezahlen
Ratenzahlung - Bequem in Raten per Bankeinzug zahlen
```

**Note: In case Payment Method names are changed manually in the JTL Shop, please update accordingly in the JTL Wawi.**


### Create Invoice Workflow

1. Navigate to the Admin -> JTL-Workflows
2. Select **Rechnungen** tab
3. Select Rechnungen -> Erstellt -> Rechnungen_Erstellt workflow
4. Configure condition with "One condition met" (Eine Bedingung erfüllt")
   1. Rechnungen\Auftrag\Zahlungsart\Name **Enthalt** Rechnungskauf - jetzt kaufen, später bezahlen
   2. Rechnungen\Auftrag\Zahlungsart\Name **Enthalt** SEPA-Lastschrift - jetzt kaufen, später per Bankeinzug bezahlen
   3. Rechnungen\Auftrag\Zahlungsart\Name **Enthalt** Ratenzahlung - Bequem in Raten per Bankeinzug zahlen
5. Configure action
   1. Web-Request POST:
      1. URL:
         ```
         http://{SHOP-URL}/mondu-api?return=invoice-create&webhooks_secret={WEBHOOK SECRET}
         ```
      2. Parameter:
         ```
         gross_amount_cents={{ Vorgang.Auftrag.Positionen.BruttopreisGesamt2 }}&net_amount_cents={{ Vorgang.Auftrag.Positionen.NettopreisGesamt2 }}&invoice_id={{ Vorgang.Auftrag.Rechnung.InterneRechnungsnummer }}&order_id={{ Vorgang.Auftrag.ExterneAuftragsnummer }}
         ```
      3. Header:
         ```
         Content-Type: application/x-www-form-urlencoded
         ```

### Cancel Invoice Workflow

1. Navigate to the Admin -> JTL-Workflows
2. Select **Rechnungen** tab
3. Select Rechnungen - Manuell, create new Event with "Ereignis anlegen" button
4. Create new event
4. Configure condition with "One condition met" (Eine Bedingung erfüllt")
   1. Auftrag\Zahlungsart\Name **Enthalt** Rechnungskauf - jetzt kaufen, später bezahlen
   2. Auftrag\Zahlungsart\Name **Enthalt** SEPA-Lastschrift - jetzt kaufen, später per Bankeinzug bezahlen
   3. Auftrag\Zahlungsart\Name **Enthalt** Ratenzahlung - Bequem in Raten per Bankeinzug zahlen
6. Configure action
   1. Web-Request POST:
      1. URL:
         ```
         http://{SHOP-URL}/mondu-api?return=cancel-invoice&webhooks_secret={WEBHOOK SECRET}
         ```
      2. Parameter:
         ```
         invoice_number={{ Vorgang.Rechnungsnummer }}
         ```
      3. Header:
         ```
         Content-Type: application/x-www-form-urlencoded
         ```

### Cancel Order Workflow

1. Navigate to the Admin -> JTL-Workflows
2. Select **Auftrage** tab
3. Select Auftrag -> Storniert and create a workflow
4. 4. Configure condition with "One condition met" (Eine Bedingung erfüllt")
   1. Zahlungen\Zahlungsart\Name **Enthalt** Rechnungskauf - jetzt kaufen, später bezahlen
   2. Zahlungen\Zahlungsart\Name **Enthalt** SEPA-Lastschrift - jetzt kaufen, später per Bankeinzug bezahlen
   3. Zahlungen\Zahlungsart\Name **Enthalt** Ratenzahlung - Bequem in Raten per Bankeinzug zahlen
5. Configure action
   1. Web-Request POST:
      1. URL:
         ```
         http://{SHOP-URL}/mondu-api?return=cancel-invoice&webhooks_secret={WEBHOOK SECRET}
         ```
      2. Parameter:
         ```
         order_number={{ Vorgang.Stammdaten.ExterneAuftragsnummer }}
         ```
      3. Header:
         ```
         Content-Type: application/x-www-form-urlencoded
         ```

![image](https://user-images.githubusercontent.com/97665980/228552408-cf45d35d-9c62-4248-9ee8-fbf5aa6a7aa9.png)

### Configure Invoice Template

1. Open your JTL-Wawi and navigate to the `Admin -> Druck- / E-Mail- / Exportvorlagen`
2. Find `Rechnung` item under Root and click `Bearbeiten` to edit invoice template
![image](https://user-images.githubusercontent.com/97665980/228816366-a6f06146-1192-41d1-b865-91ed23db7f24.png)
3. Under Payment blocks, copy any of the payment block and then paste it under payment blocks table
![image](https://user-images.githubusercontent.com/97665980/228816849-19e999a5-411e-4f24-ae3a-fe1ae8487811.png)
4. Change the name to Payment with Mondu Invoice
![image](https://user-images.githubusercontent.com/97665980/228817059-c6479e6d-f696-40b9-8228-3a7f233888a1.png)
5. Right click on this block and choose "Edit element or block" 
6. Change the block structure to match the one in the screenshot
![image](https://user-images.githubusercontent.com/97665980/228817809-ea7b7018-42fc-40d3-8021-38bb8c20716b.png)
7. Copy the text below and change the values as needed:
```
"Diese Rechnung wurde abgetreten gemäß den Allgemeinen Bedingungen von [MERCHANT] und Mondu GmbH zum Modell Kauf auf Rechnung. Wir bitten um schuldbefreiende Zahlung auf folgendes Konto:

Kontoinhaber: Mondu Capital Sàrl

IBAN: Merchant spezifische IBAN

BIC: HYVEDEMME40

Verwendungszweck: " + Report.InvoiceNumber + "

Zahlungsziel: [XX] Tage"
```
8. Change the display condition for created payment block
![image](https://user-images.githubusercontent.com/97665980/228818661-37db896a-c724-40ed-a614-d5a9af462192.png)

```
Report.PaymentMethodName = "Rechnungskauf - jetzt kaufen, später bezahlen"
```

9. Repeat all of the steps for other payment methods. Please refer to the `Mondu Invoice Snippets` for text blocks in Mondu Documentation.

# Development

**JTL Shop**

1. Download and configure MAMP for MacOS or XAMPP for Windows
2. [Download and install JTL 5 Shop community version](https://guide.jtl-software.de/jtl-shop/jtl-shop-kauf-editionen/jtl-shop-neu-installieren/) and install on the local server
3. Upload plugin through admin dashboard or clone the repository in the JTL Shop's **/plugins** directory

**JTL Wawi**

1. [Download JTL Wawi](https://www.jtl-software.de/jtl-wawi-download)
2. Use Parallels Desktop with Windows 10, 11 or similar alternatives to install JTL Wawi on MacOS environment
3. Connect the JTL Wawi with JTL Shop
4. Configure the JTL-Workflow
