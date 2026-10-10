# IntelliWare: A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support for Shea and Nicole's General Merchandise Store

A Capstone Project presented to the College of Computer Studies of Pamantasan ng Lungsod ng Pasig, in partial fulfillment of the requirements for the degree of Bachelor of Science in Information Technology.

**Authors:** Batara, Stephen Kyle B.; Santos, Milan Franco L.; Suarez, Alyssa Mae G.
**Adviser:** Federico G. Nueva, MT
**Date:** July 2026

> Note: Figures in the original Word document (architectural framework, DFD, system screenshots) are not included in this text version. Their captions and descriptions are kept inline.

---

# CHAPTER 1: Introduction

## The Problem and its Background

Every type of business relies heavily on inventory management; however, this aspect of running a business is especially important to the success of general merchandise stores due to the number and variety of goods that must be consistently available to serve their customers’ needs. There are many different categories of products found in general merchandise stores, such as Household

Items, Personal Care Items, School Supplies, Snacks and Beverages, Cleaning Supplies, and Many Other Types of Consumer Goods, all which require proper inventory upkeep, tracking, and order replenishing. In comparison to most other types of businesses with limited product offerings, general merchandise retailers must manage a very large volume of inventory with varying levels of demand at the same time, making inventory control much more challenging, as well as more critical to the operational success of the business. As a result, general merchandise retailers must have a reliable, organized, and accurate system to maintain up-todate stock records, monitor the movement of inventory throughout the store, and document all daily inventory transactions. If a proper inventory management system is not implemented, retailers will experience stock-outs, overstocked items, misplaced products, and inaccurate inventory records, all leading to disruptions to their operations, reduction of profitability and lower levels of customer satisfaction.

Several retailers still use manual inventory management techniques such as notebooks, paper-based recording systems, and basic spreadsheets. In the early stages of a business operation, these types of inventory management techniques work fine; however, as the products being managed and the quantity of transactions increases, managing inventory manually becomes increasingly complicated. Many local areas have been found to have many micro-business owners using outdated, manual, and unsystematic methods of tracking their inventory, making it extremely difficult for them to be efficient and grow (Gaborno et al., 2025). Additionally, there is a large possibility of making an error using manual methods while recording the inventory, which includes incorrect counts of items that exist in stock, missed entries, duplicate records of the same item(s), and delays in updating existing stock records. These types of errors result in stock imbalances, having to buy an item quickly at a higher price than paid for if bought earlier, and lower overall profits for the business (Puican Rodríguez et al., 2025). Having an inventory system that requires a manual process makes it more difficult for store owners and managers to track their inventory performance and create accurate reports to help them make good decisions regarding their business. As a result, frequently, high demand items are out of stock; slow-moving items are overstocked; and space is not used efficiently for storing items.

Due to increasing advancements in technology, computerized inventory management systems are effective at improving inventory control and operational efficiency. Such systems can provide real-time updates on inventory and process transactions much more rapidly than traditional methods. In addition, computerized inventory management systems have the capacity to provide more accuracy in stock management by having real-time data processing and the ability to automate updates concerning inbound and outbound inventory. In fact, there have been numerous studies conducted demonstrating that using computerized systems, businesses have been able to significantly reduce processing time by eliminating baseline counting errors made by people (Gong & Hui, 2024). These studies confirm that manual inventory systems are prone to inefficiency and error.

This is evident in Shea & Nicole's General Merchandise Store, a small - medium retail store in Pineda, Pasig City selling a wide variety of consumer goods, including household items, personal care products, school supplies, snacks and beverages, cleaning supplies, alcoholic beverages, canned goods, and other daily necessities. The store operates 24 hours a day and serves an estimated 250 to 300 customers daily, with alcoholic beverages and canned goods being among its most frequently purchased products. The store is owned by Novy D. Espiritu, with three employees who manage sales, restocking, and inventory manually without any computer systems. Manual counting and handwritten logs are used to track inventory levels, and the details of deliveries and sales transactions by hand and written. Physical inventory counting is conducted weekly, and business reports, such as inventory and sales summaries, are prepared monthly. With the increase in the number of items sold and the number of transactions, this primitive method has become harder to cope with. Surrounding their current manual inventory management processes, which affect the generation of reliable business reports as well as daily retail operations and overall customer experience. Because there is no reliable way of tracking inventory, employees struggle to determine available stock levels, when to immediately re-order specific items, and what products are overstocked/slow-moving/out of stock. These challenges lead to lost sales opportunities, wasted inventory, and delays in fulfilling customer needs.

To resolve these issues, the researchers suggested creating IntelliWare: A

Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support. A computerized inventory management system that not only automates the process of managing inventory but will also employ data analytics techniques and rule-based classification techniques for the overall effectiveness of the system. Data analytics will allow for descriptive, predictive, and prescriptive analysis of historical transactional data so that purchasing habits can be identified as well as fast- and slow-moving products, thus allowing store owners and/or store managers to determine what items to reorder based upon purchasing patterns and to receive automated replenishment notifications once stock falls below a set threshold. Rule-based classification will utilize pre-defined rules for categorizing inventory data and analyzing inventory data thus allowing for meaningful information to be provided to assist with planning for inventory levels and making business decisions regarding inventory. To support product registration and stock updates from anywhere in the store, IntelliWare will also feature a mobile-responsive web interface with image recognition capabilities, allowing staff to identify and register products from their phone's browser by capturing a photo of the item instead of relying solely on manual entry. In addition, role-based access control will be used for providing users with access to specific functions based on their job responsibilities (i.e., staff, manager, or administrator/owner). Therefore, employees will only have access to those features that relate directly to their job responsibilities.

## Review of Related Literature

Inventory management is one of the most critical business processes because it guarantees the availability of products when customers need them, and it prevents excessive stockpiling. Many small and medium-sized businesses still use hand-written stock record keeping notebooks or spreadsheets, leading to inaccurate records, late updates, insufficient stock, and overstocked inventory. To solve these problems, researchers created computerized inventory management systems that simplify recording and monitoring of inventory transactions, improve the accuracy of records, and help improve the quality of business decisions. The following literature outlines some existing inventory management systems and technologies that serve as the basis for the development of IntelliWare: A DataDriven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support.

Dr. Uma and Kaviya (2026) developed a Smart Inventory Management

System, a web-based program designed for small and medium-sized retail businesses, using PHP and MySQL technologies. The developed system consists of modules for authentication, inventory management, product categories, sales billing, reporting, role-based access for administrators, managers, and cashiers. The researchers' aim was to solve some of the challenges incurred by manual inventory recordings like inaccurate stock records, expired products, and inefficiency in monitoring. Their findings revealed that the implementation of an automated inventory system improved the accuracy of stock tracking significantly and minimized manual encoding errors. The study shows that the implementation of structured and automated inventory management systems can improve inventory management and operational efficiency in retail businesses.

Similarly, Sumacot et al. (2026) developed a web-based inventory management system for the Business Affairs and Resource Generating Office of Southern Leyte State University–Hinunangan Campus. The system centralizes the recording and monitoring of supplies, equipment, and materials into a single online platform, accessible for authorized personnel. The researchers observed that the traditional manual inventory management processes often led to inconsistent records and challenges in monitoring the availability of resources. Upon the implementation of the system, it enhanced the organization of recorded inventory items, monitoring, and accuracy of resources. As a local Philippine capstone project, the study provides evidence that web-based inventory management systems are practical and effective solutions for organizations that are still dependent on manual inventory processes.

Gong and Hui (2024) presented an Intelligent Inventory Control System that uses automation and real-time data processing for continuous updating of the inventory records with inventory upon transactions happen. The study found that automation decreased processing time, reduced human errors, and increased visibility of inventory movement. Likewise, Tanaman et al. (2023) developed a webbased inventory system that centralizes all inventory records in a single database for users to get access to updated information at any time. Their findings emphasized the improvement of inventory monitoring, consistency of records, and operational efficiency with the centralized system in storing inventory information. Both studies show that automation and centralized databases are necessary features for enhancing the accuracy of inventory and mitigating some of the limitations of traditional manual inventory management.

Wei et al. (2023) emphasized the need for combining inventory management functions into a single system. According to their study, integrating stock monitoring, inventory records, and resource management gives businesses the opportunity to effectively and efficiently monitor the inventory level and the available resources, optimizing and improving operational decisions based on more accurate information. The researchers concluded that integrated inventory management systems lower the operational issues while enhancing inventory planning and resources' optimization. The researchers' findings show the development of inventory systems providing comprehensive inventory monitoring, instead of performing separate manual procedures.

The reviewed system-related literature shares the common goal of implementing centralized and automated inventory management systems to replace manual inventory recording to better inventory accuracy, operational efficiency, and decision making. The focused of Dr. Uma and Kaviya (2026) and Sumacot et al. (2026) is in complete web-based inventory management systems, but Gong and Hui (2024), Tanaman et al. (2023), and Wei et al. (2023) emphasize the automation, centralized database, and integrated inventory management. Despite their differences, all studies recognized the limitations of manual inventory recording. However, all the reviewed systems do not specifically integrate camerabased product recognition, bulk product import, data analytics, rule-based classification, demand forecasting in one integrated inventory management system, designed specifically for a general merchandise store. This research gap provides the foundation for developing IntelliWare that integrates these features, providing more accurate management of and more sophisticated decision support.

From a technical point of view, Eweoya et al. (22025) developed a WebBased Inventory Management System, using PHP, HTML, CSS, JavaScript, and MySQL. The researchers implemented a structured software development process that included requirement analysis, system design, implementation, and deployment of the system. The developed system allows real-time inventory monitoring, automated low inventory stock notifications, role-based user access, and a responsive web interface. Researchers' findings showed that the development of the system contributed to a significant reduction of inventory errors with improved workflow efficiency. The demonstrated study shows that PHP and MySQL are reliable and cost-effective technologies for the development of inventory management systems like IntelliWare.

Timotius (2025) focused on integrating barcode scanning technology into an inventory management application. The study checked the system in three phases: functional test, response-time test, and usability evaluation. The results showed that the usage of barcode scanning accelerated inventory transactions improved accuracy of stock scanning and significantly minimized manual manual input of inventory information into database. The system was developed as a mobile inventory application, and its findings reinforce the broader principle that automated product identification, rather than manual encoding, increases the speed and accuracy of inventory recording while minimizing human error in stock transactions. IntelliWare applies this same principle through a trained camerabased image recognition model instead of barcode scanning, allowing staff to identify and log products using the camera of a mobile phone, tablet, or PC without the need for dedicated scanning hardware.

Šušter et al. (220255) discussed the importance of securing MySQL databases through proper security measures, including the management of user privileges, encryption of communication, schedule database backups, and the replication of databases. The researchers indicated that proper setup and database maintenance ensure the security of MySQL databases by limiting the risk of unauthorized access and data loss. Because of storing inventory information, transaction records, user accounts, in IntelliWare, it is necessary to apply these security practices to ensure the integrity, confidentiality, and reliability of data information and database systems.

Kumar et al. (2024) explored the use of Artificial Intelligence for demand forecasting by analyzing historical inventory and sales records to predict future demand more accurately than traditional forecasting methods. Similarly, Mamar et al. (2026) utilized predictive analytics together with point-of-sale data to improve inventory planning and reduce stock shortages. Both studies concluded that datadriven forecasting enables businesses to optimize inventory levels and improve operational performance. However, the researchers also noted that forecasting accuracy depends on the availability of sufficient historical transaction data, making continuous data collection an important requirement for intelligent inventory systems.

The reviewed technical literature demonstrates that modern inventory systems rely on affordable and proven technologies such as PHP, MySQL, barcode scanning, database security, and predictive analytics. Eweoya et al. (2025) and Timotius (2025) focused on improving inventory data collection through web technologies and barcode scanning, while Šušter et al. (2025) emphasized protecting stored information through secure database management. Kumar et al. (2024) and Mamar et al. (2026) concentrated on using historical data to support intelligent inventory forecasting. Together, these studies provide the technical foundation needed to develop a secure, efficient, and data-driven inventory management system.

Overall, both the system and technical-related literature provide a strong foundation for the development of IntelliWare. The reviewed studies consistently show that automated inventory systems enhance the accuracy of inventory management, lower manual errors, enhance the efficiency of operations, and provide better business decisions. However, current studies generally have a narrower focus on specific features such as centralized inventory management, bar code scanning, database security, or forecasting individually. IntelliWare resolves this gap by integrating these technologies into a comprehensive inventory management system designed for a general merchandise store. Through the integration of role-based access control, camera-based product recognition, data analytics, rule-based classification, demand forecasting, and secure database management, the proposed system aims to provide a more efficient, accurate, and intelligent solution for inventory management and decision support.

Before moving to the next chapter of the study, it is important to define another technique used in the study, which is rule-based classification. This technique involves using a specific set of conditions to sort or classify data, which is different from techniques that require a model to learn from the data. In addition, while machine learning classification methods need large amounts of labeled historical data and require a significant amount of time to learn how to classify data, rule-based classification uses fixed thresholds and reasoning. Specifically, rulebased classification assumes that there are certain explicit conditions defined by a person (for example, "If current stock is less than reorder point, classify it as Low Stock"). For the purposes of IntelliWare, this technique was selected because aspects of stock, such as low stock, excess stock, and stock about to expire, are defined by business rules rather than patterns. Therefore, this technique is easier to implement, monitor, and operate by the owner and staff in their work.

The study utilizes a number of terms related to inventory management. Reorder Point (ROP) refers to the amount of stock at which a new order should be issued so that an item is not understocked. Days of Supply (DOS) refers to the estimated time in days during which current stock of a given product will last considering the average daily sale of that product. Fast-Moving, Medium-Moving, Slow-Moving, and Non-Moving are terms used to classify inventory according to how quickly it is sold in relation to other goods in the store. Overstock is related to excess stock, which is excessive for the item essential to meet demand throughout a certain period while Stockout refers to the situation when a good is entirely expended.

## Significance of the Study

This study is significant because it serves **Shea and Nicole's General Merchandise Store**, its customers, employees, management, researchers, and future researchers in several ways. By replacing manual procedures with an automated and structured system, the proposed solution will enhance **Shea and Nicole's General Merchandise Store's** inventory management processes. It will contribute to the accuracy, real-time updating, and efficient management of inventory records, allowing the business to maintain organized and reliable stock information.

By utilizing the system, the store can reduce inventory-related losses, prevent stock shortages and overstocking, and minimize errors in tracking and documenting products. Through the integration of data analytics techniques, the system can identify which products are frequently purchased and which products move slowly, enabling the store to better understand customer demand. These insights can support more effective inventory planning, improve stock replenishment decisions, and enhance overall business operations.

**For customers**, the system will contribute to better and more reliable service. Since the store will be able to maintain appropriate stock levels, customers are more likely to find the products they need without delays or inconvenience. The system will also help ensure that inventory information remains accurate and up to date, reducing situations where products are unavailable despite being recorded as in stock. Faster transaction processing and improved inventory accuracy will enhance customer satisfaction and strengthen trust in the business.

**For store staff**, the system will make daily tasks easier and more efficient. Instead of relying on manual recording methods, employees can quickly input, update, and monitor inventory data using the system. This reduces the likelihood of human error and saves valuable time during daily operations. Using the mobileresponsive web interface, staff can also register products and log stock changes directly from their phone browser using image recognition, rather than being tied to a desktop terminal. The implementation of role-based access control will further assist staff by limiting access to features according to their assigned responsibilities. This improves usability, enhances security, and reduces confusion, particularly for new users.

**For store management**, the system will provide useful reports and analytical summaries that support informed decision-making. Through the data collected and processed by the system, management can monitor inventory movement, identify fast-moving and slow-moving products, evaluate stock performance, and plan restocking activities more effectively. These capabilities help improve inventory control, reduce unnecessary expenses, optimize storage utilization, and ensure that high-demand products remain available to customers.

**For the researchers**, this study will enhance their knowledge and skills in developing information systems that address real-world business challenges. It will also provide practical experience in applying data analytics and rule-based classification techniques within an inventory management environment. Through this study, the researchers will gain a deeper understanding of how technology can be utilized to improve operational efficiency, inventory accuracy, and decision making in a retail business setting.

Finally, this study can serve as a valuable reference for future researchers who intend to develop similar inventory management systems. It provides insights into the design and implementation of inventory solutions that incorporate data analytics and rule-based classification techniques. Future studies may further enhance the system's functionality, expand its analytical capabilities, or adapt it to other business sectors. Ultimately, this study aims to contribute an effective approach for improving inventory control and service quality within small and medium-sized retail businesses.

## Statement of the Problem

Shea and Nicole's General Merchandise Store experiences difficulties in managing its inventory due to its reliance on manual inventory management processes. These challenges affect the accuracy of inventory records, efficiency of daily operations, monitoring of stock levels, and the overall quality of customer service. Specifically, this study seeks to answer the following questions:

- What inventory management system can be developed for Shea and Nicole's General Merchandise Store to ensure accurate and up-to-date inventory records?

- How to effectively monitor stock levels to prevent stock shortages and overstocking of products?

- How can data analytics and rule-based classification be integrated into the proposed system to classify product movement, identify seasonal demand patterns, and analyze inventory conditions?

- How can the proposed system provide intelligent decision support by generating inventory reports, automated reorder alerts, and inventory insights for better decision-making?

- How can rule-based classification automatically evaluate inventory conditions and trigger reorder alerts?

- How can the system identify and present seasonal demand patterns of products to allow the store to prepare inventory ahead of peak periods?

- How acceptable is the developed system based on the ISO/IEC 25010:2023 Software Quality Model?

## Scope and Limitation of the Study

The study focuses on the development of IntelliWare, an inventory management system that allows store staff to effectively record, monitor, and manage products and inventory transactions of Shea and Nicole's General Merchandise Store. The system enables Administrator, Manager, and Staff users to create, edit, or remove product records through manual entry or through a mobile-responsive web interface with image recognition capabilities, accessible through a smartphone's browser, which allows staff to identify and register products and log stock changes by capturing a photo of the item, reducing the volume of manual paperwork and improving the accuracy and efficiency of the inventory update process. The web-based dashboard is designed to be responsive and accessible across devices, including desktop computers, tablets, and smartphones, allowing store owners and staff to monitor inventory status and receive alerts regardless of which device they are using. IntelliWare tracks the movement of goods to identify which products are fast-moving and which are slowmoving, applies data analytics techniques—descriptive analytics to summarize current and past inventory conditions, predictive analytics to forecast future product demand, and prescriptive analytics to recommend appropriate restocking actions—for analyzing historical transaction data, and uses a rulebased product classification module to automatically flag inventory conditions such as low stock, overstock, and near-expiration items. When the stock level of a product falls below a store-defined threshold, the system automatically triggers a replenishment notification, delivered through on-screen dashboard alerts as well as email and SMS notifications, so staff can reorder before the item runs out. The system also provides a centralized dashboard summarizing inventory status, product movement trends, and alerts, and generates reports such as inventory status reports, fast-moving and slow-moving product reports, stock replenishment reports, and expiration monitoring reports to support store owners and managers in inventory-related decision-making. The system utilizes Role-Based Access Control (RBAC) with three defined roles—Administrator (Store Owner), Manager, and Staff—to provide security and role-based system access, with each role limited to the functions relevant to its responsibilities. The system will be developed using Visual Studio Code as the integrated development environment (IDE), using the programming languages PHP and JavaScript for system development, using Python to implement the data analytics components, and using MySQL for database management, with XAMPP as the local server environment.

This study is limited to inventory management within the store environment and does not include point-of-sale (POS) or sales transaction processing, online ordering capabilities, integration with suppliers, customer relationship management, and delivery services. Furthermore, while the system features predictive demand forecasting, its accuracy is constrained by the availability of historical transaction data, requiring a minimum of 60 days of recorded sales before the most advanced seasonal models can be fully utilized. Inventory is also tracked at the batch level, with each stock-in transaction recording a batch number, manufacturing date, and expiry date, and the system automatically releases stock in First-Expired, First-Out (FEFO) order whenever a stock-out transaction occurs.

The image recognition feature identifies frequently stocked products using the system's trained recognition model, while products that fall outside this trained set are automatically read and parsed using Google Gemini Multimodal Vision AI, which extracts details such as the product name, brand, category, unit of measure, and description directly from a photo of the packaging. Items without any visible packaging or labels, such as unlabeled loose items, must still be registered through manual entry.

# CHAPTER 2: Methodology

In this chapter, the researchers describe how IntelliWare - an inventory management system for Shea and Nicole’s General Merchandise Store - was developed. The researchers go over the research methods and steps taken to plan, design, develop, and test the IntelliWare inventory management system. The various types of research designs and types of data used are also described in this chapter, along with the data flow diagrams and algorithms used to develop IntelliWare and the types of development methodologies used to create the system. The population and sample selected for this research study and the ethics followed throughout the research study are also included.

## Research Design

A developmental research design is utilized in this study, which involves developing, constructing, and testing a functional solution for an identified problem. Developmental research designs are often utilized in the development of software systems as well as in the development of development items.

The primary goal of the research is to develop an inventory management system for Shea and Nicole’s General Merchandise Store; a developmental research design is appropriate for this project, rather than looking at the development of a problem in nature or the establishment of an event. In addition to using a developmental research design for this study, descriptive research is also used to identify the way that Shea and Nicole's General Merchandise Store currently manages its inventory and to help solve any issues that the store is currently experiencing with inventory management. After collecting the descriptive research, the researchers created an inventory management system to help facilitate easier operations for Shea and Nicole's store.

Shea and Nicole's General Merchandise Store have many products for sale that include, but are not limited to, packaged food items, bottled beverages, hygiene products, cleaning supplies and items for school. The store currently carries approximately 400 or more products across these product lines. Although most products are not perishable, many still need to have an expiry date monitored. Because there are so many kinds of products in the store, it can be difficult to keep track of how much stock an item has, check on the expiry date of an item, know which items sell quickly and/or to ensure that products do not run out or have excessive quantities. The proposed system is meant to resolve these issues through automating processes, providing product classification, and generating reports that are helpful.

Researchers used the Agile Development Method, Scrum Framework, to develop the system. The Agile Methodology works by developing the system in steps through small work periods called sprints. The Agile Method is chosen because it allows the development team to make changes based on user input from store owners and staff at any point throughout the development life cycle. By being in proximity with the system users, the researchers can ensure that the resulting deliverable will meet the true requirements of the store.

## Architectural Framework

The architectural framework of IntelliWare gives an overview of the data flow in the application and an understanding of how different parts of the system function. The flow of information, starting from user authentication and product registration to analysis of items and reporting, is illustrated and explained.

The process begins with the user when he/she logs into IntelliWare through one of its three role-based accounts: Administrator, Manager, or Staff. Administrators and Managers primarily access the system through the web-based dashboard, while

Staff can access the same dashboard through a mobile-responsive web interface on their phone's browser to perform stock transactions and register products using the built-in camera. All users need to log in via the Authentication and Access Control Module before proceeding with any action, making sure that any operation performed is traceable to the relevant user.

After verifying their identity, users can make use of the Product Registration Module to register the products they have. At the time of product registration, information related to the product is provided by the user. Some of the key information includes the product name, category, selling price, supplier, quantity of products, expiry date, and other important aspects. The module allows registering products with the help of the camera using IntelliWare's developed image recognition model via the built-in camera of mobile or any computer device. By placing the product in front of the camera, the module recognizes the product in its database and makes necessary entries. For products that are not yet part of the system's trained recognition set, IntelliWare uses Google Gemini Multimodal Vision AI to read the product's packaging directly from the photo and automatically extract details such as the product name, brand, category, unit of measure, and description to pre-fill the registration form. This means that there is less effort involved in data entry, and errors remain low.

All data which has been collected in terms of inventory-wise, either through product registration or through product recognition via cameras, is stored in the central database. The database acts as a storehouse of all the details of products, inventory transactions, and users, sales history, and forecasting data. The database is a locally hosted MySQL database running on the store's XAMPP server, and the web dashboard and the mobile-responsive interface gather and update inventory information in real time as long as the accessing device is connected to the store's local network. Stock is also tracked at the batch level, with each batch recording its batch number, manufacturing date, and expiry date, allowing the system to automatically release stock in First-Expired, First-Out (FEFO) order whenever a stock-out transaction occurs.

When inventory information has been stored, it is processed by the Data Analytics Engine. This module assesses prior inventory data and transaction records to discover patterns of purchases, movements of products, and customer demands. The knowledge obtained is very helpful for planning inventory and making decisions in business. Instead of merely observing the data manually, it is derived by the system in an automatic way.

After being processed, the data needs to be analyzed using the Rule-Based Classification Engine. The module takes inventory rules that have been previously defined and applies to them to categorize items in stock according to their present conditions in the inventory and how they move in it. The categories are formed based on the rate of product movement (like Fast Moving, Moderately Moving, and SlowMoving products) through inventory. The system can detect products that have run low, those that have an excess presence in the inventory, and those that need to be reordered.

To improve inventory planning processes further, IntelliWare has implemented a Machine Learning Forecasting Module. Based on the historical sales and inventory data received from the database and results from the Data Analytics Engine, the module can predict the future product demand, recognize seasonal demand trends, calculate forecast accuracy and provide suggestions about the replenishment of stock. Using this data gives the owners and managers the opportunity to foresee their inventory needs and avoid potential problems related to inventory, such as shortages, overstocking, and so on.

The information is processed further and directed to the Alert and Reporting Module. This module generates inventory reports, sales reports, notifications of low stock, overstock alerts, and the information of inventory products nearing expiration. The reports give timely information regarding the performance of the inventory and alert the concerned in case of any urgent requirements related to the inventory.

Finally, with the help of the Dashboard Module, all the processed information, analysis results, forecasts, alerts, and reports are displayed. The dashboard gives users a complete overview of the current inventory situation, stock amounts, transaction history, demand forecasts, accuracy of forecasts, suggestions, and various reports. Via the dashboard, the store owners and managers can easily monitor the performance of their business, evaluate inventory state, and make meaningful decisions regarding the inventory, based on the information provided by the system in real-time.

IntelliWare's architectural framework includes the approach utilized for integrating the suite of authentication, image-based product identification, registration of products, local database management, data analysis, rule-based classification, machine learning forecasting, reporting, and dashboard visualization into a single comprehensive inventory management system. The integration of these various interconnected components results in an innovative inventory management system which increases the accuracy of inventory, enhances the effectiveness of the inventory monitoring system, introduces intelligent forecasting capabilities, and provides effective decision-making support for Shea and Nicole’s General Merchandise Store.

**Database**

 *Figure 1. Architectural Framework of **IntelliWare** *

## Level 0 DFD

The Level 0 Data Flow Diagram (DFD), or Context Diagram, represents the IntelliWare system as one high-level abstraction that does not contain any internal details of the system and defines the boundaries around the IntelliWare System (i.e., the user and any external users). By defining boundaries around a system, all users, including non-technical, such as staff and store owners, will have a complete understanding of the overall purpose of the IntelliWare system and how it fits within its external environment.

The external entity that interacts with IntelliWare in the Level 0 Data Flow Diagram is the User, who holds one of three roles: (1) Administrator (Store Owner), (2) Manager, or (3) Staff. The Administrator and Manager primarily access the system through the web-based dashboard, providing login credentials and receiving inventory status updates, automated alerts, and analytical reports in return. Staff primarily interact with the system through the mobile-responsive web interface on their phone's browser, providing login credentials and product or stock transaction data, including photos captured through the camera-based image recognition feature, and receiving confirmation of recorded stock changes. The Level 0 DFD is shown in Figure 2.

*Figure 2: Level 0 Data Flow Diagram of **IntelliWare** *

## Algorithm Discussion

Product and transaction data used by both algorithms can be captured either through manual entry or by using the device's camera to automatically identify a product through IntelliWare's trained image recognition model. As soon as a product is recognized by the camera, the system will obtain the matching product record from the database, thereby minimizing data entry mistakes and reducing inventory transaction entry time. This scanned data is processed the same way as manually entered data, ensuring consistency in how the Data

Analytics and Rule-Based Classification algorithms analyze and classify inventory. IntelliWare uses three main algorithms: the Data Analytics and Pattern Analysis algorithm, the Rule-Based Classification algorithm, and the Machine Learning

Forecasting algorithm. The Data Analytics and Rule-Based Classification algorithms are developed with PHP and work directly with the transaction records saved in the MySQL database, while the Forecasting algorithm is implemented in Python. Together, they convert raw metrics about inventory into useful information, such as product classification, trend reports, demand forecasts, expiry alerts, and stock statuses.

The first thing the data analytics algorithm does is count and total all sales transactions conducted on that item during the past 30 days; this becomes the monthly total sales figure. The script then divides the monthly figure by 30 to get the average units sold per day, referred to as Daily Sales Velocity. The formula used, in this case, is:

Daily Sales Velocity = Monthly Sales ÷ 30

After calculating Daily Sales Velocity, the system provides a calculation of how long Current Stock will last be based on daily sales of that product. The formula for determining how many days of supply is available is:

Days of Supply = Current Stock ÷ Daily Sales Velocity

Products with a Daily Sales Velocity of '0' and have stock on hand will be set to 999 days of supply, indicating the item has no demand and they do not know how long the stock will last. Products with both Daily Sales Velocity and Stock equal to '0' will be set to '0' days of supply.

In the data analytics module, products have been divided into categories based on their sales volume per month. The classification for products can increase, such as Fast Moving, 50 or more units, Medium Moving, between 10 and 49 units, Slow Moving, between 1 and 9 units, and Non-Moving, no sales. Fast Moving products can be quickly restocked on a regular basis as they tend to sell out regularly. Medium Moving products are sold at a regular sales rate, while Slow Moving products run the risk of piling in inventory, and Non-Moving products either have no sales or may be removed or to need to be restocked or recycled out.

The Rule-Based Classification algorithm uses a simple set of If-Then rules to classify products into four classes based on their current product levels. The logic of this application/algorithm is:

IF Current Stock ≤ 0 → Out of Stock

ELSE IF Current Stock < Reorder Point → Low Stock

ELSE IF Days of Supply > 90 → Overstock

ELSE → Normal

The system also serves as a monitor for expiration dates of products that have them, along with stock level checks. If the expiration date is within a 30-days, it triggers an alert to store staff. This allows action to be taken before a product fully expires, such as discounting the item to sell, returning to supplier, or removing it from the shelf. This is critical for expired products and items that have a long shelf life, such as packaged foods and beverages. Items with an expiration date can create loss if not monitored as well.

In addition to expiration monitoring, IntelliWare tracks inventory at the batch level. Each incoming delivery is recorded as a separate batch with its own batch number, manufacturing date, and expiry date. When stock is released for sale or other stock-out transaction, the system automatically deducts inventory using a First-Expired, First-Out (FEFO) rule, drawing first from the batch with the nearest expiry date. This reduces the likelihood of older stock expiring unsold and keeps expiration alerts aligned with the specific batches that are actually at risk.

For demand forecasting, IntelliWare applies a hybrid modeling approach based on how much historical data is available for a given product. When at least 60 days of transaction history are available, the system uses Facebook Prophet, a time-series model that accounts for weekly and yearly seasonality, to project future demand. For products with less historical data—between 7 and 59 days—the system instead uses a Random Forest Regressor built on lag and rolling-window sales features, which can produce reasonable forecasts without requiring a full season of data. Forecast accuracy is evaluated using Mean Absolute Error (MAE), Root Mean Squared Error (RMSE), and Mean Absolute Percentage Error (MAPE), with overall accuracy expressed as Accuracy = 100% − MAPE. This hybrid approach allows the Machine Learning Forecasting Module to provide reliable demand projections even for newer products that do not yet have a full season of sales history.

All three algorithms run automatically when a user accesses the system or completes a sale or transaction. Thus, the store will always know the status of its inventory, as well as notify any products that require attention.

## Software Engineering (SE) Paradigm

IntelliWare uses Agile Software Development, characterized by the development of projects using iterative phases called sprints. Agile involves the process of creating the complete system in small increments or iterative phases, allowing the team to organize regular meetings with the store owner and store staff to make contributions, requests and feedback about the overall progress of the system development.

This approach will help distribute the project development of a project such as IntelliWare, as there was a lot of variability in user behavior regarding the use of different elements of the system, including rules of classification, alert settings, and displays.

Furthermore, Scrum framework breaks down all expected functionalities into a series of “sprints,” during which each sprint consists of both planning and review meetings. During the meeting, both the required functionalities to be delivered by the end of the sprint and their priorities using MoSCoW method (MustHave, Should-Have, Could-Have, and Won't-Have) are identified to make sure the developers pay attention to the most important feature from the list at first. The goals of the sprints are provided below:

In Sprint 1, the infrastructure of the system and the user login processes were developed, and in Sprint 2, the database for inventory and fundamental product management was developed. During Sprint 3, data analytic and rulebased classification modules were developed, including methods of tracking expiry dates and in Sprint 4, all system components like dashboard, reporting functionality, and hot alerts were developed.

Furthermore, the researchers conducted brief meetings every day to monitor progress, provide information, and overcome obstacles. This helped to keep the development moving on schedule and to clear up issues immediately. The use of Agile techniques ensured the prompt creation of an operational system with fewer problems than it would have been possible with a more conventional method of development.

## Population and Sampling

The population of participants for this study includes the store owner and employees of Shea and Nicole’s General Merchandise Store who are responsible for managing and monitoring the inventory of the store. These persons will be the primary users of IntelliWare and are the individuals directly involved in the inventory management process. In total, there will be ten (10) participants who are IT professionals. They will provide an evaluation of the functionality, usability, and overall performance of the system according to pre-defined software quality standards. The number of ten (10) IT professional evaluators was chosen because a group of this size is generally considered sufficient in software evaluation practice to surface the majority of functionality, usability, and reliability issues in a system of this scope, while still being practical to coordinate given the time and resource constraints of the study. The specific number of ten (10) IT professional evaluators was set based on the recommendation of the researchers' adviser, who advised that this number would be adequate to gather sufficiently diverse and reliable technical feedback on the system given the scope and timeline of the study.

The researchers used purposive sampling as their sampling method to select participants for this study. This method is appropriate in that the researchers can select individuals who have appropriate experience and expertise in inventory management and information technology, which is relevant to the study. The researchers have selected the owners and employees of the store based upon their daily involvement with the store’s inventory management and the IT professionals based upon their education and experience in developing systems, evaluating software, or similar experiences.

The study involves fourteen (14) participants: the store owner, three (3) employees of the store, and ten (10) IT professionals. The store owner and staff will be providing data related to existing inventory management processes and assisting in identifying the store's operational requirements; by obtaining their input, IntelliWare can guarantee the system will meet the necessary requirements of the store. Meanwhile, the IT professionals will evaluate the technical quality, functionality, reliability, and usability of the system being developed by IntelliWare. After their evaluations are completed, the IT professionals will deliver recommendations to improve the developed inventory management system.

## Data Collection Methods

Data collection for this study was done using three methods: semistructured interviews, direct observation, and a survey utilizing a questionnaire based on the ISO/IEC 25010 Software Quality Standard.

The primary sources of information obtained by the researchers regarding current handling of inventory at the store were semi-structured interviews and direct observations as these provided the researchers with first-hand accounts from both the store owner and the employees regarding their experiences, and the researchers were able to observe how inventory was handled daily.

The researchers conducted interviews with the store owner and employees to assess current inventory systems and identify challenges faced by them. The researchers developed tool type questions for guided interviews but allowed spare time for further exploration of useful topics as they were found during the interview process. The interviews focused on five topic areas: how does the store record and monitor inventory; what challenges do they have; how do they maintain sales/publication records of product usage; what is the total number of products being managed by the store; the greatest benefit a new system could provide would be.

During multiple visits to the store, the researchers were able to observe how inventory-related tasks are conducted, such as receiving shipments, placing stock in storage, updating sales/publication records, etc. The visits allowed the researchers to identify additional findings that may not have been discussed during their previous interviews, including scattered records and unlabelled products. The researchers kept notes of the processes and documentation (e.g., notebooks, spreadsheet applications) used to complete the tasks during each visit.

The requirements for IntelliWare, both functional and non-functional, were defined from the data collected during the interviews and observations. Functional requirements describe what the system needs to do, including recordkeeping for inventory, generating low-stock and near-expiry alerts, creating reports, performing data analytics, and managing user access. Non-functional requirements describe how well the system needs to perform (i.e., usability, reliability, accuracy, and security).

An evaluation of IntelliWare using a questionnaire was performed after the completion of the system. The questionnaire was adapted from the ISO/IEC 25010 Software Quality Model and covered six quality characteristics: Functionality, Reliability, Usability, Efficiency, Portability, and Maintainability. Security was assessed through an item on unauthorized access under Functionality, and Compatibility was not included in this evaluation. Each of the fourteen participants rated each item on a four-point scale (4 – Strongly Agree, 3 – Agree, 2 – Disagree, 1 – Strongly Disagree) and was given the opportunity to provide written comments. The questionnaire was distributed to all fourteen participants through a Google Forms link.

## Data Analysis Procedures

The data collected from the questionnaire is analyzed using descriptive statistics. To calculate overall ratings based on respondents' answers to Likerttype questionnaires, these ratings were calculated using the weighted mean for each quality domain.

The formula used is:

```
Weighted Mean = Σ(f × x) / N
```

*(The formula appeared as an equation image in the original document; reconstructed here from the variable definitions below.)*

Where:

- f = frequency of responses

- x = scale value

- N = total number of responses

The results were interpreted using the following scale:

| **Range** | **Interpretation** |
| --- | --- |
| 3.26 – 4.00 | Strongly Agree |
| 2.51 – 3.25 | Agree |
| 1.76 – 2.50 | Disagree |
| 1.00 – 1.75 | Strongly Disagree |

The overall weighted mean of the system was computed as the average of the six category weighted means.

## Ethical Considerations

The researchers follow the ethical guidelines and standards to ensure everyone's rights, privacy, and safety during the research study.

The researchers received consent from the store owner and all employees prior to collecting any data. They informed each participant of the study's purpose, their role in the study, how their data will be used, and their right to withdraw at any time with no negative impact. There was no coercion or pressure on any of the participants to give consent.

The participants' privacy is always protected during the study. The personal information collected by the research team will only be used for the study and will not be shared with individuals who are not part of the research study. All storespecific data such as inventory records were handled in a confidential manner and will not be made available to the public. At the conclusion of the study, the research team secured all data collected because of the study and properly disposed of it.

The researchers were also open and honest in their relationship with the participants during the entirety of the research project; therefore, the collected data were all accurate records of how the participants had represented their inputs. The researchers provided the participants with updates on the progress of the study and an opportunity to check the system specifications based on their answers to the survey questions.

IntelliWare was built for the benefit of the users. IntelliWare does not collect that type of information concerning the customers of its retailers; rather, the processes are set in such a way as to control the access to sensitive information by utilizing a controlled method of access based upon the user's role. This demonstrates the researchers' desire to conduct a responsible research study and to treat all participants in the study with respect.

## Summary

This chapter presented various approaches employed to develop

IntelliWare for Shea and Nicole’s Store. This was done with the purpose of creating a usable system; thus, the project used a developmental research design since there were no natural conditions in which to conduct this type of research project. The Scrum method was selected to guide the development process as it permitted the team to develop in phases (iterations) and allow for continual implementation of user feedback received from the owner and employees of the store.

IntelliWare’s architectural structure links all parts of the system; therefore, users input data and the resulting reports and alerts are used to manage the inventory of the store as one continuous process. The three main algorithms in IntelliWare, the Data Analytics and Pattern Analysis algorithm, the Rule-Based Classification algorithm, and the Machine Learning Forecasting algorithm, produce information about classified products, demand forecasts, and real-time updates on stock level and expiration date status.

The interview technique, along with observational studies and evaluation questionnaires based on ISO/IEC 25010 standards, was used to collect data. Then, the researchers calculated the overall quality of the system by means of a weighted mean analysis of the collected data. In addition, the researchers maintained ethical standards during the entire study, thereby ensuring that the privacy and rights of all individuals involved were preserved. All these methods of data collection were utilized to establish that IntelliWare is a viable, dependable, and beneficial solution to the inventory management problems faced by Shea and Nicole's Store.

# CHAPTER 3: Results and Discussions

This chapter presents the results from testing and evaluating IntelliWare, a data-driven inventory management system that uses data analytics and rule-based classification to support decision-making at Shea and Nicole's General Merchandise Store, which previously struggled with inaccurate records, stock monitoring, and restocking under its manual processes. It describes the system's capabilities, the testing process, and the data gathered from the System Evaluation Tool. Results are organized around the Statement of the Problem, covering record accuracy, stock-level monitoring, analytics and classification integration, decision support, reorder alerts, seasonal demand identification, and overall acceptability based on ISO/IEC 25010:2023.

## Project Capabilities and Testing Process Details

IntelliWare was built as seven connected modules that worked on one shared MySQL database. A user first logged in through the Authentication and Access Control Module, which determined the functions that the user could open. Products were then registered manually or through the camera, and every record and transaction was saved in the database. The Data Analytics Engine and the Rule-Based Classification Engine processed these records to classify products and detect stock problems, the Machine Learning Forecasting Module projected future demand, and the Alert and Reporting Module and the Dashboard Module presented the results to the owner, managers, and staff.

The database was hosted locally on the store's XAMPP server, and the web dashboard and the mobile-responsive interface read and updated the inventory information in real time if the device was connected to the store's local network. Administrators and Managers mainly used the web-based dashboard, while Staff used the mobile-responsive interface on their phone's browser to record stock transactions and to register products with the camera.

## System Features and Capabilities

**Authentication and Access Control Module**

The module was the entry point of every session. Users logged in with their username or email address and password, which the system checked against the user records in the central database; matching credentials opened the dashboard for the user's role (Administrator, Manager, or Staff), while invalid credentials were rejected and no inventory data was shown. Role-Based Access Control (RBAC) attached permissions to the role, so Administrators had full access while Managers and Staff could use only the functions that matched their responsibilities.

**Figure 3: IntelliWare Login Page **

Figure 3 presents the IntelliWare login page, which was the entry point of the system. It contained the fields for the username or email address and the password, a link for password recovery, and the Login button. A user who supplied valid credentials was directed to the dashboard that matched his or her role, while invalid credentials were rejected and no inventory data was displayed.

**Product Registration Module**

The module was where every inventory record began, allowing authorized users to create, edit, or remove product records through manual entry or camera-based recognition. In manual entry, users typed the product details, with the reorder point preset to 10 units and stock recorded by batch (batch number, manufacturing date, and expiry date) for First-Expired, First-Out release. In camera-based registration, a photo was matched by the trained recognition model or, for products outside that set, read by Google Gemini Vision to pre-fill the name, brand, category, unit of measure, and description. Users then reviewed the details and saved the record to the MySQL database, where camera data and manual data were processed the same way.

### Figure 4: Add New Product Modal

 Figure 4 presents the Add New Product form used for manual entry. The upload and take-photo options appeared at the top of the form, followed by the three groups of fields described above. The reorder point was preset to 10 units and could be changed per product, since this value was later used by the rule-based classification to trigger low-stock alerts. The Save Product button recorded the product in the database, and the form could also be completed using the keyboard shortcuts displayed at the bottom.

### Figure 4.1 Sample Added Product

Figure 4.1 presents a sample product being added through the camera-based feature. After the photo of the product was selected, the system displayed a notice that Google Gemini Vision was analyzing the packaging. The user could retake the photo if the image was unclear. Once the analysis was completed, the recognized details were placed in the corresponding fields of the form, and the user reviewed them before saving the product.

**Data Analytics Engine**

The engine, written in PHP, worked directly on the transaction records in the MySQL database and ran automatically whenever a user accessed the system or completed a transaction. It totaled each product's sales over the previous 30 days, divided this by 30 to get the Daily Sales Velocity, and divided current stock by the velocity to get the Days of Supply (999 for products with stock but no sales, and 0 for no sales and no stock). The results were summarized by product, category, sales trend, supplier, and expiry, served as the basis of the Rule-Based Classification Engine and the Forecasting Module, and were shown in the Data Mining and Analytics page.

**Figure 5: Data Analytics**

Figure 5 presents the Data Mining and Analytics page, which served as the main view of the Data Analytics Engine. The summary cards at the top showed the total number of products, the number of low-stock and out-of-stock products, the number of fast-moving products, and the number of products expiring soon. Below the cards, the tabs for Product Analysis, Category Performance, Sales Trends, Supplier Analysis, and Expiry Alerts allowed the user to move between the different analytic views.

**Figure 5. 1 : Product Movement Analysis **

Figure 5.1 presents the Product Movement Analysis table. Each product was listed with its category, current stock, monthly sales, daily velocity, days of supply, movement class, and stock status. The user could search for a product by name and filter the list by category, movement type, and stock status, which made it possible to find slow-selling or low-stock items without going through all of the records.

**Figure 5. 2 : Category Performance **

Figure 5.2 presents the Category Performance view. The table summarized each product category by the number of products, total stock, average stock, the number of low-stock items, and sales, while the Sales Distribution graph beside it showed how sales were spread across the categories. This allowed the owner to see which categories carried the most stock and which ones were selling.

**Figure 5. 3 : Sales Trends **

Figure 5.3 presents the Sales Trends view for the last 30 days. The table listed the date, day, number of products sold, total quantity, and average quantity per transaction, and the Trend Visualization plotted the same data over time. The view showed how the store’s sales changed from day to day.

### Figure 5.4: Supplier Performance

Figure 5.4 presents the Supplier Performance view. Suppliers were listed with the number of products supplied, total stock, average stock, monthly sales, the number of low-stock items, and a status indicator. The view helped the store identify which suppliers provided products that were running low and needed to be reordered.

**Rule-Based Classification Engine**

The engine applied predefined If-Then rules to the analytics results so that each product was classified automatically. By monthly sales, products were Fast-Moving (50 or more units), Medium-Moving (10 to 49), Slow-Moving (1 to 9), or Non-Moving (no sales). Stock status was checked in a fixed order: Out of Stock (stock of zero or less), Low Stock (below the reorder point), Overstock (Days of Supply above 90), and Normal otherwise, with Low Stock triggering a reorder alert. Products expiring within 30 days were flagged and given a risk level based on the days left and the rate of sales, and First-Expired, First-Out deduction kept these alerts aligned with the batches at risk.

**Figure 6: Product Movement Analysis (Filtered with stock statuses) **

Figure 6 presents the Product Movement Analysis page filtered by movement type and stock status. In the sample shown, the Fast-Moving filter was selected, and the table listed only the products that met this classification, together with the stock status assigned by the rules. The drop-down menus showed the four movement classes that the user could choose from.

### Figure 6.1: Expiry Risk Analysis

Figure 6.1 presents the Expiry Risk Analysis. Each product with an expiration date was listed with its stock, expiry date, days left, sales, sell-through, and a risk level. The risk level was determined from the days left before expiry and the rate at which the product was selling, so that items that were unlikely to be sold before they expired could be discounted, returned to the supplier, or removed from the shelf in time.

**Machine Learning Forecasting Module**

The module, written in Python, projected future demand from historical sales and inventory data using a hybrid approach: Facebook Prophet for products with at least 60 days of history, and a Random Forest Regressor built on lag and rolling-window sales features for products with 7 to 59 days. Accuracy was measured using the Mean Absolute Error (MAE), Root Mean Squared Error (RMSE), and Mean Absolute Percentage Error (MAPE), with overall accuracy expressed as 100% minus the MAPE. The projected 30-day demand was compared with the stock on hand to recommend actions such as an urgent restock or a pause in purchase orders, and the module also used the month-by-month seasonal demand of the Philippine market to show the events and categories expected to rise in demand.

**Figure 7: 30-Day Inventory Replenishment & Demand Forecast table **

Figure 7 presents the 30-Day Inventory Replenishment and Demand Forecast table. For each of the top in-demand products, the table showed the current stock, the seasonal demand and stock intelligence (including the projected demand for the period and the number of days the stock was expected to last), and the recommended action, such as placing an urgent restock order for products that were about to run out or pausing new purchase orders for products with excess stock. The Check Stock button led the user to the record of the product concerned.

**Figure 7.1: Philippine Seasonal Market Intelligence modal popup **

Figure 7.1 presents the Philippine Market and Seasonal Demand Intelligence pop-up. The user selected the forecast horizon and the target month, and the pop-up displayed the seasonal period for that month, the events that were expected to drive consumption, and the projected demand lift per product category compared with the baseline. Below these, the Seasonal Demand and Stock Alignment table compared the products expected to be in demand with the stock currently on hand, and flagged those with a risk of stockout, so that the store could prepare its inventory ahead of the peak period.

**Alert and Reporting Module**

The module alerted users to inventory problems and produced the reports they needed, using the analytics and classification results and running automatically whenever a user accessed the system or completed a transaction. A notification showing the alert type, product name, quantity remaining, and reorder point was created when a product fell below its reorder point, became overstocked, or neared its expiration date. For reports, users chose a date range, category, and stock status to generate the inventory status, fast-moving and slow-moving product, stock replenishment, and expiration monitoring reports, which could be exported, including the full Official Inventory and Audit Report.

### Figure 8: Stock Notifications

Figure 8 presents the Stock Notifications panel. Each notification showed the alert type, the name of the product, the quantity remaining, and the reorder point that triggered the alert. In the sample shown, two products had fallen below their reorder points and were flagged as Low Stock. The Refresh option updated the list with the latest stock status.

**Figure 8.1: Reports Page **

Figure 8.1 presents the Reports page. The user set the date range, category, and stock status in the Generate Report section and pressed the search button. The summary cards then showed the number of products, the total units, the low-stock count, and the out-of-stock count for the selected filters, and the Stock Explorer listed the products by category with options to export the results.

**Figure 8.2: Sample generated report**

Figure 8.2 presents a sample generated report, the Official Inventory and Audit Report. The report header displayed the reference number, the user who generated the report, the report scope and parameters, and the inventory reconciliation. The summary boxes showed the totals for products, units in the warehouse, good stock, low stock, and out of stock, and the table below listed each product with its SKU, category, stock quantity, unit, reorder point, and health status.

**Dashboard Module**

The module was the last stage of the system, collecting the analysis results, forecasts, alerts, and reports into one view that depended on the role of the logged-in user. The Administrator dashboard showed a critical-stock warning, summary figures (total SKUs, total items, low-stock alerts, and active categories), recent stock movements, critical stock levels, top-demand products, and shortcuts for receiving and releasing inventory. The Staff dashboard was simpler, showing the total products, total quantity in stock, Stock In and Stock Out shortcuts, low-stock alerts, and stock distribution by category, with the administrative menus hidden.

### Figure 9: Admin Dashboard

Figure 9 presents the Admin Dashboard. A banner at the top warned the user of products that were critically low, and the summary cards showed the total SKUs, total items, low-stock alerts, and active categories. The Quick Access section allowed the user to receive or release inventory, the Recent Activity section listed the latest stock movements, the Critical Stock Levels section showed the products at or below their reorder points, and the Top Demand chart showed the products with the highest demand.

**Figure 9.1: Staff Dashboard **

Figure 9.1 presents the Staff Dashboard. It showed the total number of products, the total quantity in stock, and the status of the logged-in staff member. The Quick Actions section provided the Stock In, Stock Out, and View Full Inventory buttons, the Low Stock Alerts section listed the products that needed to be restocked, and the Inventory Distribution chart showed how the stock was spread across the product categories. Menu items for administrative functions such as user management and reports were not available to the staff account.

**Test Plan and Test Data **

 A test plan was prepared to outline the strategy, objectives, resources, schedule, and success criteria used to test IntelliWare, consistent with the functional and non-functional requirements identified in Chapter 2 and the Statement of the Problem in Chapter 1. Testing had two parts: (1) functional testing of each module using a prepared test data set, and (2) evaluation by fourteen (14) respondents using the System Evaluation Tool. Prior to testing, the researchers populated the system with (800) product records and over (800) transaction records so that the Data Analytics Engine, Rule-Based Classification Engine, and Forecasting Module would have sufficient data to produce meaningful output.

**System Evaluation Instrument **

Following functional testing, IntelliWare was evaluated by fourteen (14) respondents, the store owner, three (3) store employees, and ten (10) IT professionals, using a System Evaluation Tool adapted from the ISO/IEC 25010 Software Quality Model (ISO/IEC, 2011). The instrument contained fifteen (15) items grouped under six quality characteristics: Functionality (3 items), Reliability (2), Usability (4), Efficiency (2), Portability (2), and Maintainability (2). Each item was rated on a four-point scale (1 to 4), and respondents were given space to provide written comments.

**Present the Results **

## Table 2. Scale of Weighted Mean and Verbal Interpretation

| **Weighted Mean Average ** | **Verbal Interpretation ** |
| --- | --- |
| 3.26 – 4.00 | Strongly Agree |
| 2.51 – 3.25 | Agree |
| 1.76 – 2.50 | Disagree |
| 1.00 – 1.75 | Strongly Disagree |

Table 2 presented the scale used to interpret the weighted means. A mean from 3.26 to 4.00 was interpreted as Strongly Agree, 2.51 to 3.25 as Agree, 1.76 to 2.50 as Disagree, and 1.00 to 1.75 as Strongly Disagree. In the succeeding tables, the Total Weight was the sum of the ratings given to an item by a group, and the Weighted Mean was the Total Weight divided by the number of respondents in that group. The Category Weighted Mean was the total of the item weights divided by the number of respondents multiplied by the number of items in the category.

## Table 3. Distribution of Respondents According to Profile

| **Respondent Type ** | | **Frequency ** | **Percentage ** |
| --- | --- | --- | --- |
| Owner and Staff | 4 | | 28.57% |
| IT Professionals | 10 | | 71.43% |
| Total | 14 | | 100% |

Table 3 presented the profile of the fourteen (14) respondents. The owner and staff group had four (4) respondents, or 28.57% of the total. It consisted of the store owner (7.14%), who used the system as its administrator to monitor inventory and decide on restocking, and three (3) store employees (21.43%), who used the system in their daily work to record stock transactions and register products. The IT professionals’ group had ten (10) respondents, or 71.43% of the total, who evaluated the system from a technical point of view

## Functionality

**Table 4 . Weighted Means for Functionality**

| **Item ** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall Weig t ed Mean** | **Verbal Interpretation ** |
| --- | --- | --- | --- | --- |
| Can the software perform the tasks required? | 3.50 | 3.75 | 3.57 | Strongly Agree |
| Is the result as expected? | 3.80 | 4.00 | 3.86 | Strongly Agree |
| Does the software prevent unauthorised access? | 3.60 | 4.00 | 3.71 | Strongly Agree |
| **Category Weighted Mean ** | 3.63 | 3.92 | 3.71 | Strongly Agree |

Table 4 presented the ratings for Functionality. The item "Is the result as expected?" obtained the highest overall weighted mean (3.86; total weight 54), followed by "Does the software prevent unauthorized access?" (3.71) and "Can the software perform the tasks required?" (3.57). The owner and staff gave a category weighted mean of 3.92, which was higher than the 3.63 given by the IT professionals, and both groups gave Strongly Agree ratings. The category weighted mean was 3.71, interpreted as Strongly Agree, and no respondent rated any item Disagree or Strongly Disagree.

## Reliability

**Table 5. Weighted Means for Reliability**

| **Item ** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall Weighted Mean** | **Verbal Interpretation ** |
| --- | --- | --- | --- | --- |
| Is the software capable of handling errors? | 3.50 | 3.50 | 3.50 | Strongly Agree |
| Can the software resume working and restore lost data after failure? | 3.50 | 3.75 | 3.57 | Strongly Agree |
| **Category Weighted Mean ** | 3.50 | 3.63 | 3.54 | Strongly Agree |

Table 5 presented the ratings for Reliability. The item on handling errors obtained an overall weighted mean of 3.50, and the item on resuming work and restoring lost data after a failure obtained 3.57. The IT professionals rated both items 3.50, while the owner and staff rated them 3.50 and 3.75. The category weighted mean was 3.54, interpreted as Strongly Agree, and no respondent rated any item Disagree or Strongly Disagree.

- **Usability Table 6. Weighted Means for Usability**

| **Item ** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall Weighted Mean** | **Verbal Interpretation ** |
| --- | --- | --- | --- | --- |
| Does the user comprehend how to use the system easily? | 3.40 | 3.50 | 3.43 | Strongly Agree |
| Can the user learn to use the system easily? | 3.60 | 3.50 | 3.57 | Strongly Agree |
| Can the user use the system without much effort? | 3.40 | 3.50 | 3.43 | Strongly Agree |
| Does the interface look good? | 3.60 | 3.50 | 3.57 | Strongly Agree |
| **Category Weighted Mean ** | 3.50 | 3.50 | 3.50 | Strongly Agree |

Table 6 presented the ratings for Usability. The items on learning the system and on the look of the interface obtained the highest overall weighted mean (3.57 each), while the items on understanding how to use the system and on using it without much effort obtained 3.43 each. The IT professionals and the owner and staff both gave a category weighted mean of 3.50. The category weighted mean was 3.50, interpreted as Strongly Agree. One respondent rated "Can the user use the system without much effort?" as Disagree.

### D. Efficiency

**Table 7. Weighted Means for Efficiency**

| **Item ** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall Weighted Mean** | **Verbal Interpretation ** |
| --- | --- | --- | --- | --- |
| How quickly does the system respond? | 3.50 | 3.00 | 3.36 | Strongly Agree |
| Does the system utilise resources efficiently? | 3.50 | 4.00 | 3.64 | Strongly Agree |
| **Category Weighted Mean ** | 3.50 | 3.50 | 3.50 | Strongly Agree |

Table 7 presented the ratings for Efficiency. The item on the efficient use of resources obtained an overall weighted mean of 3.64, while the item on how quickly the system responds obtained 3.36. The owner and staff rated the response speed 3.00 (Agree), lower than the 3.50 given by the IT professionals, but rated the use of resources 4.00. The category weighted mean was 3.50, interpreted as Strongly Agree, and no respondent rated any item Disagree or Strongly Disagree.

## E. Portability

**Table 8. Weighted Means for Portability**

| **Item ** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall Weighted Mean** | **Verbal Interpretation ** |
| --- | --- | --- | --- | --- |
| Can the software be installed easily? | 3.30 | 3.50 | 3.36 | Strongly Agree |
| Can the software easily replace other software? | 3.30 | 3.00 | 3.21 | Agree |
| **Category Weighted Mean ** | 3.30 | 3.25 | 3.29 | Strongly Agree |

 Table 8 presented the ratings for Portability. The item on easy installation obtained an overall weighted mean of 3.36 (Strongly Agree), while the item on easily replacing other software obtained 3.21, interpreted as Agree. The owner and staff rated the replacement item 3.00, and the IT professionals rated it 3.30. The category weighted mean was 3.29, interpreted as Strongly Agree, which was the lowest among the six quality characteristics. Three respondents rated the replacement item as Disagree.

## F. Maintainability

**Table 9. Weighted Means for Maintainability**

| **Item ** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall Weighted Mean** | **Verbal Interpretation ** |
| --- | --- | --- | --- | --- |
| Can faults be easily diagnosed? | 3.40 | 3.75 | 3.50 | Strongly Agree |
| Can the software be tested easily? | 3.20 | 3.50 | 3.29 | Strongly Agree |
| **Category Weighted Mean ** | 3.30 | 3.63 | 3.39 | Strongly Agree |

Table 9 presented the ratings for Maintainability. The item on diagnosing faults obtained an overall weighted mean of 3.50, while the item on testing the software easily obtained 3.29. The owner and staff gave a category weighted mean of 3.63, which was higher than the 3.30 given by the IT professionals. The category weighted mean was 3.39, interpreted as Strongly Agree. One respondent rated "Can the software be tested easily?" as Disagree.

**G. Summary of Results Table 1 0 . Summary of Weighted Means per Quality Characteristic**

| **Quality Characteristic** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall** | **Verbal Interpretation** |
| --- | --- | --- | --- | --- |
| Functionality | 3.63 | 3.92 | 3.71 | Strongly Agree |
| Reliability | 3.50 | 3.63 | 3.54 | Strongly Agree |
| Usability | 3.50 | 3.50 | 3.50 | Strongly Agree |
| Efficiency | 3.50 | 3.50 | 3.50 | Strongly Agree |
| Maintainability | 3.30 | 3.25 | 3.29 | Strongly Agree |
| Portability | 3.30 | 3.63 | 3.39 | Strongly Agree |
| **Overall Weighted Mean** | 3.47 | 3.58 | 3.50 | Strongly Agree |

Table 10 summarized the category weighted means of the six quality characteristics. Functionality obtained the highest category weighted mean (3.71) and Portability the lowest (3.29), and all six characteristics were interpreted as Strongly Agree. The owner and staff gave an overall weighted mean of 3.58, slightly higher than the 3.47 given by the IT professionals. The overall weighted mean of the system was 3.50, interpreted as Strongly Agree. Only 5 of the 210 individual ratings (about 2%) were Disagree, and none were Strongly Disagree.

# CHAPTER 4: Conclusion and Recommendations

This chapter presents the conclusions drawn from the results of the testing and evaluation of IntelliWare: A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support for Shea and Nicole’s General Merchandise Store, as reported in Chapter 3, and the recommendations for the further enhancement of the system. The conclusions answer the specific problems raised in Chapter 1 in the order in which they were stated, and each recommendation is based on the conclusions drawn.

**Conclusion**

Based on the findings of the study, the following conclusions are drawn:

- An inventory management system, IntelliWare, was developed for Shea and Nicole's General Merchandise Store to keep inventory records accurate and up to date. It brought product registration (manual or camera-based), a central database, analytics, classification, forecasting, alerts, reports, and a dashboard into one system. The evaluators rated Functionality at 3.71 (Strongly Agree), with 3.57 for performing the tasks required and 3.86 for producing the expected results.

- Stock levels were effectively monitored by classifying every product as Normal, Low Stock, Out of Stock, or Overstock from its current stock, reorder point, and Days of Supply, and by showing these conditions on the dashboards and in the notifications, so that shortages and overstocking could be seen before they became problems. The evaluators agreed that the software performed the tasks required of it (3.57, Strongly Agree).

- Data analytics and rule-based classification were integrated by computing the Daily Sales Velocity and Days of Supply from the transaction records and applying If-Then rules to classify products by movement (Fast-Moving, Medium-Moving, Slow-Moving, and Non-Moving) and by stock condition, while the forecasting module identified seasonal demand. The evaluators rated the results as expected at 3.86, the highest item mean in the evaluation.

- The system provided intelligent decision support by generating inventory status, fast-moving and slow-moving, stock replenishment, and expiration reports, by issuing automated low-stock, overstock, and near-expiration alerts, and by presenting inventory insights through role-based dashboards. The evaluators rated the results as expected at 3.86.

- Rule-based classification automatically evaluated inventory conditions by applying the If-Then rules to each product whenever a user accessed the system or completed a transaction, and a product that fell below its reorder point was classified as Low Stock and flagged with a notification showing the quantity remaining and the reorder point. Reliability was rated at 3.54 (Strongly Agree), which shows that these processes ran dependably.

- The system identified and presented seasonal demand patterns through the Machine Learning Forecasting Module and the Philippine Market and Seasonal Demand Intelligence view, which showed the seasonal period, the events expected to drive consumption, the projected demand lift per category, and the products at risk of stockout, so that the store could prepare its inventory ahead of peak periods. The System Evaluation Tool had no item specific to seasonal demand, so this conclusion rests on the Functionality ratings (3.57 and 3.86).

- The developed system was acceptable based on the ISO/IEC 25010 Software Quality Model, since all six quality characteristics were rated Strongly Agree: Functionality (3.71), Reliability (3.54), Usability (3.50), Efficiency (3.50), Portability (3.29), and Maintainability (3.39). The overall weighted mean was 3.50. The lowest-rated items were easily replacing other software (3.21, Agree), testing the software easily (3.29), and the response speed of the system (3.36).

Overall, the results of the evaluation were Strongly Agree (3.50), which shows that IntelliWare is acceptable to its users and is a workable replacement for the store's manual inventory recording.

**Recommendations Based on the foregoing conclusions, the following further enhancements are recommended:**

- IntelliWare may be extended with a point-of-sale module so that stock levels update automatically with every sale, and with supplier ordering so that a reorder alert can lead directly to a purchase order.

- The forecasting and seasonal demand features may be enhanced by tracking the forecast accuracy the system reports as more sales history accumulates, and by including an evaluation item for seasonal demand in future studies.

- A bulk import tool and a guided setup may be added so that other stores can bring in their existing spreadsheet or notebook records and adopt the system quickly.

- Automated testing and detailed technical documentation may be added to support smooth maintenance and future updates, and database queries may be optimized so the system stays fast as transactions grow.

- Future researchers may adapt IntelliWare to other retail and business sectors, building on its data analytics, rule-based classification, and camera-based registration.

## References

Dr. Uma, S., & Kaviya, P. (2026). Smart inventory management system: A webbased solution for automated retail inventory operations. In International Journal of Current Science (IJCSPUB) (Vol. 16, Issue 1, pp. 444–445) [Journalarticle]. https://rjpn.org/ijcspub/papers/IJCSP26A1055.pdf

Eweoya, I., Adigun, T., Akinduro, F., Oladipo, S., Awoniyi, A., Okorie, G., Sodiq, K., Lawal, O., & Fabiyi, O. (2025). Design and implementation of a web-based inventory management system. Current Trends in Information Communication

Technology Research, 4(1). https://doi.org/10.61867/pcub.v4i1a.200 Gaborno, J. A. B., Ernal, J. G. G., Gange, M. S. R. L., Mucho, J. G. P., Cariaga, K. G., Ibieza, D. B., Egael, L. J. J., & Gentapanan, R. G. (2025). Microbusiness owners' inventory management practices. International Journal of Research and Innovation in Social Science, 9(3). https://dx.doi.org/10.47772/IJRISS.2025.909000373

Gong, H., & Hui, H. (2024). Research on intelligent inventory management system based on automation and real-time data processing. Mathematical Modeling and Algorithm Application, 3(2), 39–43 https://doi.org/10.54097/4yafsk85

Kumar, P., Choubey, D., Amosu, O. R., & Ogunsuji, Y. M. (2024). AI-enhanced inventory and demand forecasting: Using AI to optimize inventory management and predict customer demand. World Journal of Advanced Research and Reviews, 23(1), 1931–1944. https://doi.org/10.30574/wjarr.2024.23.1.2173

Mamar, R. R. M. C., Dairo, C. F. T., Maravilla, E. C., Pisuena, J. S., & Soberano, K. T. (2026). A Data-Driven POS system for sales monitoring and inventory management. Zenodo (CERN European Organization for Nuclear Research). https://doi.org/10.5281/zenodo.20412208

Puican Rodríguez, V. H., Toro López, R. D. J., & García Vera, W. R. (2025).

Innovations in inventory management to improve the profitability of local SMEs. F1000Research, 14, 853. https://doi.org/10.12688/f1000research.168900.3

Sumacot, C., Matubay, A., Ruiz, A., Lariosa, M., & Dadap, R. (2026). Design and development of a web-based inventory management system for the Business Affairs and Resource Generating Office of SLSU-Hinunangan Campus [Capstone project]. Southern Leyte State University. Studocu. https://www.studocu.com/ph/document/polytechnic-university-of- thehttps://www.studocu.com/ph/document/polytechnic-university-of-thephilippines/bachelor-of-science-in-information-technology/design-and-developmentof-an-inventory-management-system-for-bargo-itcapstone/161073857?sid=695731041784283644philippines/bachelor-of-science-ininformation-technology/designandhttps://www.studocu.com/ph/document/polytechnic-university-of-thephilippines/bachelor-of-science-in-information-technology/design-and-developmentof-an-inventory-management-system-for-bargo-itcapstone/161073857?sid=695731041784283644development-of-an-inventorymanagement-system-for-bargo-ithttps://www.studocu.com/ph/document/polytechnicuniversity-of-the-philippines/bachelor-of-science-in-information-technology/designand-development-of-an-inventory-management-system-for-bargo-it- capstone/161073857?sid=695731041784283644capstone/161073857?sid=695731 041784283644

Šušter, I., Karabašević, D., Stanujkić, D., Popović, G., Veljić, A., & Marković, M. (2025). Data protection and security in MySQL database. Quaestus, 27(1), 125–

137. https://www.ceeol.com/search/article-detail?id=1393381

Timotius, E. (2025). Mobile application development for inventory management using barcode technology. International Journal of Emerging Financial and Economic Research, 4. https://wikep.net/index.php/IJEFE/article/view/77/73

Tanaman, M., Lloyd, J., Baylosis, A., Abiles, J., Lester, M., Catungal, P., & Encarnacion, P. (2023). Web-based inventory management system. International Journal of Science and Applied Information Technology, 12(5), 44–48. https://doi.org/10.30534/ijsait/2023/021252023

Wei, C. C., Ramiah, S. A., & Razali, N. F. (2023). Inventory Management Systems (IMS). The Journal of Applied Technology and Innovation, 7(3). https://doi.org/10.65136/jati.v7i1.129