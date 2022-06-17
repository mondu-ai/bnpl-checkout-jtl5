# Plugin

JTL 5 integration plugin for Mondu Payment.

#### Installation

1. Download .zip folder from the **main** branch on Github repository
2. Navigate to the JTL Shop administration dashboard
3. Navigate to the Plug-in manager from the sidebar
4. Choose **Upload** tab and upload the downloaded plugin file
5. Navigate to the **Available** tab and install the *Mondu Payments* plugin

#### Configuration

**Configure API**

1. Navigate to the JTL Shop administration dashboard
2. Expand **Installed plug-ins** menu item on the left side and choose Mondu Payment
3. Configure the fields:
   * API Sandbox Mode: Select yes to point the plugin to the sandbox environment
   * Fill in API Secret
   * Fill in Webhooks Secret

**Configure JTL Shop**

1. Navigate to the Shipments -> Shipping Methods
2. Select desired Shipping method and click on edit icon
3. Enable Mondu Payment in the **ACCEPTED PAYMENT METHODS** section

**Configure JTL Wawi**

1. Navigate to the Admin -> JTL-Workflows
2. Select **Rechnungen** tab
3. Select Rechnungen -> Erstellt -> Rechnungen_Erstellt workflow
4. Configure condition
   1. Rechnungen\Auftrag\Zahlungsart\Name **Gleich** Mondu Payment
5. Configure action
   1. Web-Request POST:
      1. URL:
         ```
         http://{SHOP-URL}/mondu-api?return=invoice-create
         ```
      2. Parameter:
         ```
         gross_amount_cents={{ Vorgang.Auftrag.Positionen.BruttopreisGesamt2 }}&net_amount_cents={{ Vorgang.Auftrag.Positionen.NettopreisGesamt2 }}&invoice_id={{ Vorgang.Auftrag.Rechnung.InterneRechnungsnummer }}&order_id={{ Vorgang.Auftrag.ExterneAuftragsnummer }}
         ```

`<img width="1007" alt="image" src="https://user-images.githubusercontent.com/97665980/174281478-7d96ed59-67d9-42dc-8355-486ebb9f1cca.png">`


# Development

**JTL Shop**

1. Download and configure MAMP for MacOS or XAMPP for Windows
2. [Download and install JTL 5 Shop community version](https://guide.jtl-software.de/jtl-shop/jtl-shop-kauf-editionen/jtl-shop-neu-installieren/) and install on the local server
3. Upload plugin through admin dashboard or clone the repository in the JTL Shop's **/plugins** directory

**JTL Wawi**

1. [Download JTL Wawi](https://www.jtl-software.de/jtl-wawi-download)
2. Use Parallels Desktop with Windows 10, 11 or similar alternatives to install JTL Wawi on MacOS environment
3. Connect the JTL Wawi with JTL Shop
4. Configure the JTL-Workflows
