> **Intelliware: A Data-Driven Inventory Management System Using**

**Data Analytics and Rule-Based Classification for Decision**

**Support for Shea and Nicole’s General Merchandise Store**

A Capstone Project presented to the

College of Computer Studies of

Pamantasan ng Lungsod ng Pasig

In partial fulfillment of the requirements for the degree of

Bachelor of Science in Information Technology

by:

Batara, Stephen Kyle B.

Santos, Milan Franco L.

Suarez, Alyssa Mae G.

Federico G. Nueva, MT

Adviser

October 2026

**ADVISER’S RECOMMENDATION SHEET**

This Capstone Project entitled

> **Intelliware: A Data-Driven Inventory Management System Using**

**Data Analytics and Rule-Based Classification for Decision**

**Support for Shea and Nicole’s General Merchandise Store**

by:

Batara, Stephen Kyle B.

Santos, Milan Franco L.

Suarez, Alyssa Mae G.

And submitted in partial fulfillment of the requirements of the

Bachelor of Science in Information Technology degree

has been examined and is recommended for acceptance and approval

> \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

Federico G. Nueva, MT

Adviser

October 2026

**CAPSTONE PROJECT COORDINATOR**

**AND DEAN’S ACCEPTANCE SHEET**

This Capstone Project entitled

> **Intelliware: A Data-Driven Inventory Management System Using**

**Data Analytics and Rule-Based Classification for Decision**

**Support for Shea and Nicole’s General Merchandise Store**

by:

Batara, Stephen Kyle B.

Santos, Milan Franco L.

Suarez, Alyssa Mae G.

After having been recommended and approved is hereby accepted by

the College of Computer Studies of

The Pamantasan ng Lungsod ng Pasig

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

Noreen Arcangel-Perez, DIT

Thesis Coordinator

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

Riegie Dy Tan, DIT

CCS Dean

> October 2026

**CERTIFICATION**

This capstone project entitled **Intelliware: A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support for Shea and Nicole’s General Merchandise Store** prepared and submitted by Batara, Stephen Kyle B., Santos, Milan Franco L., & Suarez, Alyssa Mae G.. in partial fulfillment of the requirements for the degree of Bachelor of Science in Information Technology (BSIT) has been examined and recommended for ORAL EXAMINATION.

Federico G. Nueva, MT

Adviser

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

**APPROVAL SHEET**

Approved by the Panel for Oral Defense on October 18, 2026, with a rating of \_\_\_.

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_ \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

Greta M. Rosario, DIT Noreen Arcangel-Perez, DIT

Panel Member Chairman

Accepted in partial fulfillment of the degree of Bachelor of Science in Information Technology.

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

Riegie D. Tan, DIT

CCS Dean

**ACKNOWLEDGMENT**

**S.K.B.B**

**M.F.L.S**

**A.M.G.S**

**TABLE OF CONTENTS**

List of Appendices vii

List of Figures viii

List of Tables ix

Abstract x

# CHAPTER 1: Introduction 

The Problem and Its Background 3

Review of Related Literature 7

Significance of the study 13

Statement of the Problem 16

General Problem 16

Specific Problems 16

Scope and Limitation of the Study 17

**CHAPTER 2: Methodology**

Introduction 18

Research Design 19

Architectural framework 20

Level 0 DFD, or schematic diagram 25

Algorithm Discussion 26

SE Paradigm 28

Population and Sampling 30

Data Collection Methods 31

Ethical Considerations 35

**CHAPTER 3: Results and Discussion**

Introduction 18

Project Capabilities 19

Architectural framework 20

Level 0 DFD, or schematic diagram 25

Algorithm Discussion 26

SE Paradigm 28

Population and Sampling 30

Data Collection Methods 31

Ethical Considerations 35

**CHAPTER 4: Conclusion and Recommendations**

Introduction 18

Research Design 19

Architectural framework 20

Level 0 DFD, or schematic diagram 25

Algorithm Discussion 26

SE Paradigm 28

Population and Sampling 30

Data Collection Methods 31

Ethical Considerations 35

References xx

Plagiarism Check Results

Curriculum Vitae

**LIST OF APPENDICES**

**Appendix A:** Operational Definition of Terms xx

**Appendix B:** Evaluation Sheets xx

**Appendix C:** Project Timeline xx

**Appendix D:** Work Assignments xx

**Appendix E:** Certificate of Appreciation xx

**Appendix F:** Source Code xx

**Appendix G:** Plagiarism Check Results xx

**Appendix H:** AI Generated Content Check Results xx

**Appendix I:** IMRaD compressed version xx

**LIST OF FIGURES**

Figure 1: Architectural Framework xx

Figure 2: Level 0 DFD Diagram of IntelliWare xx

Figure 3: IntelliWare Login Page xx

Figure 3.1: Forgot Password xx

Figure 4: Master Inventory xx

Figure 4.1: Add New Product xx

Figure 4.2: Sample Added Product (Gemini Vision Analyzing Packaging) xx

Figure 5: Stock Transactions Page xx

Figure 5.1: Stock Receiving xx

Figure 5.2: Stock Releasing xx

Figure 5.3: Inventory Transaction History xx

Figure 6: Data Analytics xx

Figure 6.1: Product Movement Analysis xx

Figure 6.2: Category Performance xx

Figure 6.3: Sales Trends xx

Figure 6.4: Supplier Performantabce xx

Figure 7: Product Movement Analysis xx

Figure 7.1: Expiry Risk Analysis xx

Figure 8: 30-day inventory replenishment & demand forecast table xx

Figure 8.1: Philippine Seasonal Market Intelligence Modal Pop-Up xx

Figure 9: Stock Notifications xx

Figure 9.1: Reports Page xx

Figure 9.2: Sample Generated Report xx

Figure 10: Admin Dashboard xx

Figure 10.1: Manager Dashboard xx

Figure 10.2: Staff Dashboard xx

Figure 11: User Management xx

Figure 11.1: System Audit Trail xx

Figure 11.2: System Settings – General and Rules xx

Figure 11.3: System Settings – Notifications and Gateways xx

Figure 11.4: System Settings – AI Packaging Scanner xx

Figure 11.5: System Settings – AI Demand Forecasting xx

Figure 11.6: System Settings – Maintenance and Backups xx

**LIST OF TABLES**

Table 1: Likert-scale interpretation xx

Table 2: Scale of Weighted Mean and Verbal Interpretation xx

Table 3: Distribution of Respondents According to Profile xx

Table 4: Weighted Means for Functionality xx

Table 5: Weighted Means for Reliability xx

Table 6: Weighted Means for Usability xx

Table 7: Weighted Means for Efficiency xx

Table 8: Weighted Means for Portability xx

Table 9: Weighted Means for Maintainability xx

Table 10: Summary of Weighted Means per Quality Characteristic xx

**ABSTRACT**

> **Title :** Intelliware: A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support for Shea and Nicole’s General Merchandise Store
>
> **Researchers :** Batara, Stephen Kyle B.
>
> Santos, Milan Franco L.
>
> Suarez, Alyssa Mae G.
>
> **Degree :** Bachelor of Science in Information Technology
>
> **School Year :** 2026-2027
>
> **Project description:**
>
> Small general merchandise stores that still rely on handwritten logs, and spreadsheets often suffer from inaccurate stock records, stock-outs, and overstocking. This study developed IntelliWare, a web-based inventory management system that uses data analytics and rule-based classification for decision support at Shea and Nicole’s General Merchandise Store in Pineda, Pasig City. A developmental research design, supported by descriptive research through interviews and observations, was used, and the system was built using the Agile Scrum framework with PHP, MySQL, and Python. IntelliWare has ninemodules: authentication with role-based access control, product registration (manual or camera-based), a data analytics engine, a rule-based classification engine, a machine learning forecasting module, alerts and reporting, and role-based dashboards. The system was tested with 400 product records and over 400 transaction records and was evaluated by fourteen respondents, composed of the store owner, three employees, and ten IT professionals, using an instrument based on ISO/IEC 25010. All six quality characteristics were rated Strongly Agree, with an overall weighted mean of 3.50 and Functionality rated highest (3.71). The study concluded that IntelliWare keeps inventory records accurate, monitors stock levels, identifies fast- and slow-moving products and seasonal demand, and issues automated reorder alerts, making it an acceptable replacement for the store’s manual process.

**CHAPTER 1**

**Introduction**

#  The Problem and its Background 

Every type of business relies heavily on inventory management; however, this aspect of running a business is especially important to the success of general merchandise stores due to the number and variety of goods that must be consistently available to serve their customers’ needs. There are many different categories of products found in general merchandise stores, such as Household Items, Personal Care Items, School Supplies, Snacks and Beverages, Cleaning Supplies, and Many Other Types of Consumer Goods, all which require proper inventory upkeep, tracking, and order replenishing. In comparison to most other types of businesses with limited product offerings, general merchandise retailers must manage a very large volume of inventory with varying levels of demand at the same time, making inventory control much more challenging, as well as more critical to the operational success of the business. As a result, general merchandise retailers must have a reliable, organized, and accurate system to maintain up-to-date stock records, monitor the movement of inventory throughout the store, and document all daily inventory transactions. If a proper inventory management system is not implemented, retailers experience stock-outs, overstocked items, misplaced products, and inaccurate inventory records, all leading to disruptions to their operations, reduction of profitability and lower levels of customer satisfaction.

Several retailers still use manual inventory management techniques such as notebooks, paper-based recording systems, and basic spreadsheets. In the early stages of a business operation, these types of inventory management techniques work fine; however, as the products being managed and the quantity of transactions increases, managing inventory manually becomes increasingly complicated. Many local areas have been found to have many micro-business owners using outdated, manual, and unsystematic methods of tracking their inventory, making it extremely difficult for them to be efficient and grow (Gaborno et al., 2025). Additionally, there is a large possibility of making an error using manual methods while recording the inventory, which includes incorrect counts of items that exist in stock, missed entries, duplicate records of the same item(s), and delays in updating existing stock records. These types of errors result in stock imbalances, having to buy an item quickly at a higher price than paid for if bought earlier, and lower overall profits for the business (Puican Rodríguez et al., 2025). Having an inventory system that requires a manual process makes it more difficult for store owners and managers to track their inventory performance and create accurate reports to help them make good decisions regarding their business. As a result, frequently, high demand items are out of stock; slow-moving items are overstocked; and space is not used efficiently for storing items.

Due to increasing advancements in technology, computerized inventory management systems are effective at improving inventory control and operational efficiency. Such systems can provide real-time updates on inventory and process transactions much more rapidly than traditional methods. In addition, computerized inventory management systems have the capacity to provide more accuracy in stock management by having real-time data processing and the ability to automate updates concerning inbound and outbound inventory. In fact, there have been numerous studies conducted demonstrating that using computerized systems, businesses have been able to significantly reduce processing time by eliminating baseline counting errors made by people (Gong & Hui, 2024). These studies confirm that manual inventory systems are prone to inefficiency and error.

This was evident in Shea & Nicole's General Merchandise Store, a small-to-medium retail store in Pineda, Pasig City that sold a wide variety of consumer goods, including household items, personal care products, school supplies, snacks and beverages, cleaning supplies, alcoholic beverages, canned goods, and other daily necessities. The store operates 24 hours a day and serves an estimated 250 to 300 customers daily, with alcoholic beverages and canned goods among its most frequently purchased products. The store was owned by Novy D. Espiritu, with three employees who managed sales, restocking, and inventory manually without any computer systems. Manual counting and handwritten logs were used to track inventory levels, and the details of deliveries and sales transactions were written by hand. Physical inventory counting was conducted weekly, and business reports, such as inventory and sales summaries, were prepared monthly. With the increase in the number of items sold and the number of transactions, this manual method has become harder to cope with. The manual inventory management processes affected the generation of reliable business reports as well as daily retail operations and overall customer experience. Because there was no reliable way of tracking inventory, employees struggled to determine available stock levels, when to re-order specific items, and which products were overstocked, slow-moving, or out of stock. These challenges led to lost sales opportunities, wasted inventory, and delays in fulfilling customer needs.

To resolve these issues, the researchers developed IntelliWare: A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support, a computerized inventory management system that not only automated the process of managing inventory but also employed data analytics and rule-based classification techniques to improve the overall effectiveness of the system. Data analytics enabled descriptive, predictive, and prescriptive analysis of historical transactional data so that purchasing habits and fast- and slow-moving products could be identified, allowing store owners and managers to determine which items to reorder based on purchasing patterns and to receive automated replenishment notifications once stock fell below a set threshold. Rule-based classification used pre-defined rules to categorize and analyze inventory data, providing meaningful information to assist with inventory planning and business decisions. To support product registration and stock updates from anywhere in the store, IntelliWare featured a mobile-responsive web interface with camera-based product recognition, allowing staff to identify and register products from their phone's browser by capturing a photo of the item instead of relying solely on manual entry. In addition, role-based access control was used to give users access to specific functions based on their job responsibilities (i.e., staff, manager, or administrator/owner), so that employees could access only the features that related directly to their responsibilities.

# Review of Related Literature 

Inventory management is one of the most critical business processes because it guarantees the availability of products when customers need them, and it prevents excessive stockpiling. Many small and medium-sized businesses still use hand-written stock record keeping notebooks or spreadsheets, leading to inaccurate records, late updates, insufficient stock, and overstocked inventory. To solve these problems, researchers created computerized inventory management systems that simplify recording and monitoring of inventory transactions, improve the accuracy of records, and help improve the quality of business decisions. The following literature outlines some existing inventory management systems and technologies that serve as the basis for the development of IntelliWare: A DataDriven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support.

Dr. Uma and Kaviya (2026) developed a Smart Inventory Management

System, a web-based program designed for small and medium-sized retail businesses, using PHP and MySQL technologies. The developed system consists of modules for authentication, inventory management, product categories, sales billing, reporting, role-based access for administrators, managers, and cashiers. The researchers' aim was to solve some of the challenges incurred by manual inventory recordings like inaccurate stock records, expired products, and inefficiency in monitoring. Their findings revealed that the implementation of an automated inventory system improved the accuracy of stock tracking significantly and minimized manual encoding errors. The study shows that the implementation of structured and automated inventory management systems can improve inventory management and operational efficiency in retail businesses.

Similarly, Sumacot et al. (2026) developed a web-based inventory management system for the Business Affairs and Resource Generating Office of Southern Leyte State University–Hinunangan Campus. The system centralizes the recording and monitoring of supplies, equipment, and materials into a single online platform, accessible for authorized personnel. The researchers observed that the traditional manual inventory management processes often led to inconsistent records and challenges in monitoring the availability of resources. Upon the implementation of the system, it enhanced the organization of recorded inventory items, monitoring, and accuracy of resources. As a local Philippine capstone project, the study provides evidence that web-based inventory management systems are practical and effective solutions for organizations that are still dependent on manual inventory processes.

Gong and Hui (2024) presented an Intelligent Inventory Control System that uses automation and real-time data processing for continuous updating of the inventory records with inventory upon transactions happen. The study found that automation decreased processing time, reduced human errors, and increased visibility of inventory movement. Likewise, Tanaman et al. (2023) developed a webbased inventory system that centralizes all inventory records in a single database for users to get access to updated information at any time. Their findings emphasized the improvement of inventory monitoring, consistency of records, and operational efficiency with the centralized system in storing inventory information. Both studies show that automation and centralized databases are necessary features for enhancing the accuracy of inventory and mitigating some of the limitations of traditional manual inventory management.

Wei et al. (2023) emphasized the need for combining inventory management functions into a single system. According to their study, integrating stock monitoring, inventory records, and resource management gives businesses the opportunity to effectively and efficiently monitor the inventory level and the available resources, optimizing and improving operational decisions based on more accurate information. The researchers concluded that integrated inventory management systems lower operational issues while enhancing inventory planning and resources' optimization. The researchers' findings show the development of inventory systems providing comprehensive inventory monitoring, instead of performing separate manual procedures.

The reviewed system-related literature shares the common goal of implementing centralized and automated inventory management systems to replace manual inventory recording to better inventory accuracy, operational efficiency, and decision making. The focused of Dr. Uma and Kaviya (2026) and Sumacot et al. (2026) is in complete web-based inventory management systems, but Gong and Hui (2024), Tanaman et al. (2023), and Wei et al. (2023) emphasize the automation, centralized database, and integrated inventory management. Despite their differences, all studies recognized the limitations of manual inventory recording. However, all the reviewed systems do not specifically integrate camerabased product recognition, bulk product import, data analytics, rule-based classification, demand forecasting in one integrated inventory management system, designed specifically for a general merchandise store. This research gap provides the foundation for developing IntelliWare that integrates these features, providing more accurate management of and more sophisticated decision support.

From a technical point of view, Eweoya et al. (22025) developed a WebBased Inventory Management System, using PHP, HTML, CSS, JavaScript, and MySQL. The researchers implemented a structured software development process that included requirement analysis, system design, implementation, and deployment of the system. The developed system allows real-time inventory monitoring, automated low inventory stock notifications, role-based user access, and a responsive web interface. Researchers' findings showed that the development of the system contributed to a significant reduction of inventory errors with improved workflow efficiency. The demonstrated study shows that PHP and MySQL are reliable and cost-effective technologies for the development of inventory management systems like IntelliWare.

Timotius (2025) focused on integrating barcode scanning technology into an inventory management application. The study checked the system in three phases: functional test, response-time test, and usability evaluation. The results showed that the usage of barcode scanning accelerated inventory transactions improved accuracy of stock scanning and significantly minimized manual manual input of inventory information into database. The system was developed as a mobile inventory application, and its findings reinforce the broader principle that automated product identification, rather than manual encoding, increases the speed and accuracy of inventory recording while minimizing human error in stock transactions. IntelliWare applies this same principle through a trained camerabased image recognition model instead of barcode scanning, allowing staff to identify and log products using the camera of a mobile phone, tablet, or PC without the need for dedicated scanning hardware.

Šušter et al. (220255) discussed the importance of securing MySQL databases through proper security measures, including the management of user privileges, encryption of communication, schedule database backups, and the replication of databases. The researchers indicated that proper setup and database maintenance ensure the security of MySQL databases by limiting the risk of unauthorized access and data loss. Because of storing inventory information, transaction records, user accounts, in IntelliWare, it is necessary to apply these security practices to ensure the integrity, confidentiality, and reliability of data information and database systems.

Kumar et al. (2024) explored the use of Artificial Intelligence for demand forecasting by analyzing historical inventory and sales records to predict future demand more accurately than traditional forecasting methods. Similarly, Mamar et al. (2026) utilized predictive analytics together with point-of-sale data to improve inventory planning and reduce stock shortages. Both studies concluded that datadriven forecasting enables businesses to optimize inventory levels and improve operational performance. However, the researchers also noted that forecasting accuracy depends on the availability of sufficient historical transaction data, making continuous data collection an important requirement for intelligent inventory systems.

The reviewed technical literature demonstrates that modern inventory systems rely on affordable and proven technologies such as PHP, MySQL, barcode scanning, database security, and predictive analytics. Eweoya et al. (2025) and Timotius (2025) focused on improving inventory data collection through web technologies and barcode scanning, while Šušter et al. (2025) emphasized protecting stored information through secure database management. Kumar et al. (2024) and Mamar et al. (2026) concentrated on using historical data to support intelligent inventory forecasting. Together, these studies provide the technical foundation needed to develop a secure, efficient, and data-driven inventory management system.

Overall, both the system and technical-related literature provide a strong foundation for the development of IntelliWare. The reviewed studies consistently show that automated inventory systems enhance the accuracy of inventory management, lower manual errors, enhance the efficiency of operations, and provide better business decisions. However, current studies generally have a narrower focus on specific features such as centralized inventory management, bar code scanning, database security, or forecasting individually. IntelliWare resolves this gap by integrating these technologies into a comprehensive inventory management system designed for a general merchandise store. Through the integration of role-based access control, camera-based product recognition, data analytics, rule-based classification, demand forecasting, and secure database management, the proposed system aims to provide a more efficient, accurate, and intelligent solution for inventory management and decision support.

Before moving to the next chapter of the study, it is important to define another technique used in the study, which is rule-based classification. This technique involves using a specific set of conditions to sort or classify data, which is different from techniques that require a model to learn from the data. In addition, while machine learning classification methods need large amounts of labeled historical data and require a significant amount of time to learn how to classify data, rule-based classification uses fixed thresholds and reasoning. Specifically, rulebased classification assumes that there are certain explicit conditions defined by a person (for example, "If current stock is less than reorder point, classify it as

Low Stock"). For the purposes of IntelliWare, this technique was selected because aspects of stock, such as low stock, excess stock, and stock about to expire, are defined by business rules rather than patterns. Therefore, this technique is easier to implement, monitor, and operate by the owner and staff in their work.

The study utilizes a number of terms related to inventory management. Reorder Point (ROP) refers to the amount of stock at which a new order should be issued so that an item is not understocked. Days of Supply (DOS) refers to the estimated time in days during which current stock of a given product will last considering the average daily sale of that product. Fast-Moving, Medium-Moving, Slow-Moving, and Non-Moving are terms used to classify inventory according to how quickly it is sold in relation to other goods in the store. Overstock is related to excess stock, which is excessive for the item essential to meet demand throughout a certain period while Stockout refers to the situation when a good is entirely expended.

# Significance of the Study 

This study was significant because it served **Shea and Nicole's General Merchandise Store**, its customers, employees, management, researchers, and future researchers in several ways. By replacing manual procedures with an automated and structured system, the solution improved the inventory management processes of Shea and Nicole's General Merchandise Store. It contributed to the accuracy, real-time updating, and efficient management of inventory records, allowing the business to maintain organized and reliable stock information.

By utilizing the system, the store could reduce inventory-related losses, prevent stock shortages and overstocking, and minimize errors in tracking and documenting products. Through the integration of data analytics techniques, the system could identify which products were frequently purchased and which products moved slowly, enabling the store to better understand customer demand. These insights supported more effective inventory planning, improved stock replenishment decisions, and enhanced overall business operations.

**For customers**, the system contributed to better and more reliable service. Since the store could maintain appropriate stock levels, customers were more likely to find the products they needed without delays or inconvenience. The system also helped ensure that inventory information remained accurate and up to date, reducing situations where products were unavailable despite being recorded as in stock. Faster transaction processing and improved inventory accuracy were expected to enhance customer satisfaction and strengthen trust in the business.

**For store staff**, the system will make daily tasks easier and more efficient. Instead of relying on manual recording methods, employees can quickly input, update, and monitor inventory data using the system. This reduces the likelihood of human error and saves valuable time during daily operations. Using the mobileresponsive web interface, staff can also register products and log stock changes directly from their phone browser using image recognition, rather than being tied to a desktop terminal. The implementation of role-based access control will further assist staff by limiting access to features according to their assigned responsibilities. This improves usability, enhances security, and reduces confusion, particularly for new users.

**For store management**, the system provided useful reports and analytical summaries that supported informed decision-making. Through the data collected and processed by the system, management could monitor inventory movement, identify fast-moving and slow-moving products, evaluate stock performance, and plan restocking activities more effectively. These capabilities helped improve inventory control, reduce unnecessary expenses, optimize storage utilization, and ensure that high-demand products remain available to customers.

**For the researchers**, this study enhanced their knowledge and skills in developing information systems that address real-world business challenges. It also provided practical experience in applying data analytics and rule-based classification techniques within an inventory management environment. Through this study, the researchers gained a deeper understanding of how technology can be utilized to improve operational efficiency, inventory accuracy, and decision making in a retail business setting.

Finally, this study can serve as a valuable reference for future researchers who intend to develop similar inventory management systems. It provides insights into the design and implementation of inventory solutions that incorporate data analytics and rule-based classification techniques. Future studies may further enhance the system's functionality, expand its analytical capabilities, or adapt it to other business sectors. Ultimately, this study aimed to contribute an effective approach for improving inventory control and service quality within small and medium-sized retail businesses.

# Statement of the Problem 

Shea and Nicole's General Merchandise Store experiences difficulties in managing its inventory due to its reliance on manual inventory management processes. These challenges affect the accuracy of inventory records, efficiency of daily operations, monitoring of stock levels, and the overall quality of customer service. Specifically, this study seeks to answer the following questions:

1.  What inventory management system can be developed for Shea and Nicole's General Merchandise Store to ensure accurate and up-to-date inventory records?

2.  How to effectively monitor stock levels to prevent stock shortages and overstocking of products?

3.  How can data analytics and rule-based classification be integrated into the proposed system to classify product movement, identify seasonal demand patterns, and analyze inventory conditions?

4.  How can the proposed system provide intelligent decision support by generating inventory reports, automated reorder alerts, and inventory insights for better decision-making?

5.  How can rule-based classification automatically evaluate inventory conditions and trigger reorder alerts?

6.  How can the system identify and present seasonal demand patterns of products to allow the store to prepare inventory ahead of peak periods?

7.  How acceptable is the developed system based on the ISO/IEC 25010:2023 Software Quality Model?

# Scope and Limitation of the Study 

> The study focused on the development of IntelliWare, an inventory management system that allowed store staff to effectively record, monitor, and manage the products and inventory transactions of Shea and Nicole's General Merchandise Store. The system enabled Administrator, Manager, and Staff users to create, edit, or remove product records through manual entry or through a mobile-responsive web interface with camera-based product recognition, accessible through a smartphone's browser, which allowed staff to identify and register products and log stock changes by capturing a photo of the item, reducing manual paperwork and improving the accuracy and efficiency of inventory updates. The web-based dashboard was designed to be responsive and accessible across devices, including desktop computers, tablets, and smartphones, allowing store owners and staff to monitor inventory status and receive alerts regardless of the device they used. IntelliWare tracked the movement of goods to identify which products were fast-moving and which were slow-moving, applied data analytics techniques (descriptive analytics to summarize current and past inventory conditions, predictive analytics to forecast future product demand, and prescriptive analytics to recommend appropriate restocking actions) to analyze historical transaction data, and used a rule-based product classification module to automatically flag inventory conditions such as low stock, overstock, and near-expiration items. When the stock level of a product fell below its store-defined reorder point, the system automatically triggered a replenishment notification, delivered through on-screen dashboard alerts as well as email and SMS notifications, so that staff could reorder before the item ran out. The system also provided a centralized dashboard summarizing inventory status, product movement trends, and alerts, and generated reports such as inventory status reports, fast-moving and slow-moving product reports, stock replenishment reports, and expiration monitoring reports to support store owners and managers in inventory-related decision-making. The system utilized Role-Based Access Control (RBAC) with three defined roles (Administrator or Store Owner, Manager, and Staff) to provide security and role-based system access, with each role limited to the functions relevant to its responsibilities. The system was developed using Visual Studio Code as the integrated development environment (IDE), with PHP and JavaScript for system development, Python for the machine learning forecasting component, and MySQL for database management, with XAMPP as the local server environment.
>
> This study was limited to inventory management within the store environment and did not include point-of-sale (POS) or sales transaction processing, online ordering, integration with suppliers, customer relationship management, or delivery services. The study covered 400 products registered in the system database. Furthermore, although the system featured predictive demand forecasting, its accuracy was constrained by the availability of historical transaction data, with a minimum of 60 days of recorded sales required before the most advanced seasonal model could be fully utilized. Inventory was also tracked at the batch level, with each stock-in transaction recording a batch number, manufacturing date, and expiry date, and the system automatically released stock in First-Expired, First-Out (FEFO) order whenever a stock-out transaction occurred.
>
> The image recognition feature identified frequently stocked products using the system's trained recognition model, while products that fell outside this trained set were automatically read and parsed using Google Gemini Multimodal Vision AI, which extracted details such as the product name, brand, category, unit of measure, and description directly from a photo of the packaging. Items without any visible packaging or labels, such as unlabeled loose items, still had to be registered through manual entry.

**CHAPTER 2**

**Methodology**

In this chapter, the researchers described how IntelliWare, an inventory management system for Shea and Nicole's General Merchandise Store, was developed. The researchers went over the research methods and steps taken to plan, design, develop, and test the IntelliWare inventory management system. The research design and the types of data used were also described in this chapter, along with the data flow diagrams, the algorithms used to develop IntelliWare, and the development methodology used to create the system. The population and sample selected for the study and the ethics followed throughout the study were also included.

# Research Design 

A developmental research design was utilized in this study, which involved developing, constructing, and testing a functional solution for an identified problem. Developmental research designs are often utilized in the development of software systems as well as other developed products.

The primary goal of the research was to develop an inventory management system for Shea and Nicole's General Merchandise Store; a developmental research design was appropriate for this project, rather than examining the development of a problem in nature or the establishment of an event. In addition to the developmental research design, descriptive research was also used to identify how Shea and Nicole's General Merchandise Store managed its inventory and to understand the issues the store experienced in inventory management. After collecting the descriptive data, the researchers created an inventory management system to facilitate easier operations for Shea and Nicole's store.

Shea and Nicole's General Merchandise Store had many products for sale, including, but not limited to, packaged food items, bottled beverages, hygiene products, cleaning supplies, and school items. The store carried approximately 400 products across these product lines, and 400 products were registered in the IntelliWare database. Although most products were not perishable, many still needed their expiry dates monitored. Because of the number of kinds of products in the store, it was difficult to keep track of how much stock an item had, check the expiry date of an item, know which items sold quickly, and ensure that products did not run out or have excessive quantities. IntelliWare was meant to resolve these issues by automating processes, providing product classification, and generating helpful reports.

The researchers used the Agile Development Method, Scrum Framework, to develop the system. The Agile methodology developed the system in steps through small work periods called sprints. The Agile method was chosen because it allowed the development team to make changes based on user input from the store owner and staff at any point throughout the development life cycle. By staying in close proximity to the system users, the researchers ensured that the resulting deliverable met the true requirements of the store.

# Architectural Framework 

The architectural framework of IntelliWare gave an overview of the data flow in the application and an understanding of how the different parts of the system functioned. The flow of information, starting from user authentication and product registration to analysis of items and reporting, was illustrated and explained.

The process began with the user logging into IntelliWare through one of its three role-based accounts: Administrator, Manager, or Staff. Administrators and Managers primarily accessed the system through the web-based dashboard, while Staff accessed the same dashboard through a mobile-responsive web interface on their phone's browser to perform stock transactions and register products using the built-in camera. All users had to log in via the Authentication and Access Control Module before proceeding with any action, making sure that any operation performed was traceable to the relevant user.

After verifying their identity, users could use the Product Registration Module to register products. At the time of product registration, the user provided information related to the product, such as the product name, category, selling price, supplier, quantity, expiry date, and other important details. The module allowed products to be registered with the help of the camera through IntelliWare's trained image recognition model, using the built-in camera of a mobile or any computer device. When a product was placed in front of the camera, the module recognized the product in its database and made the necessary entries. For products that were not yet part of the system's trained recognition set, IntelliWare used Google Gemini Multimodal Vision AI to read the product's packaging directly from the photo and automatically extract details such as the product name, brand, category, unit of measure, and description to pre-fill the registration form. This reduced data entry effort and kept errors low.

All data collected for inventory, whether through product registration or camera-based product recognition, was stored in the central database. The database served as the storehouse of all details of products, inventory transactions, users, transaction history, and forecasting data. The database was a locally hosted MySQL database running on the store's XAMPP server, and the web dashboard and the mobile-responsive interface retrieved and updated inventory information in real time as long as the accessing device was connected to the store's local network. Stock was also tracked at the batch level, with each batch recording its batch number, manufacturing date, and expiry date, allowing the system to automatically release stock in First-Expired, First-Out (FEFO) order whenever a stock-out transaction occurred. Stock receiving and releasing were handled by the Daily Inventory Transactions and Batch Tracking Module, while the System Governance and Administration Module allowed the administrator to manage user accounts, review the audit trail, and configure system settings.

Once inventory information had been stored, it was processed by the Data Analytics Engine. This module assessed past inventory data and transaction records to discover patterns of purchases, product movement, and customer demand. The knowledge obtained was helpful for inventory planning and business decision-making. Instead of relying on manual observation of the data, the system derived this information automatically.

After processing, the data was analyzed by the Rule-Based Classification Engine. The module took predefined inventory rules and applied them to categorize items in stock according to their present inventory conditions and how they moved. The categories were formed based on the rate of product movement (Fast-Moving, Medium-Moving, Slow-Moving, and Non-Moving products). The system could detect products that had run low, those that were overstocked, and those that needed to be reordered.

To improve inventory planning further, IntelliWare implemented a Machine Learning Forecasting Module. Based on the historical sales and inventory data received from the database and the results of the Data Analytics Engine, the module predicted future product demand, recognized seasonal demand trends, calculated forecast accuracy, and provided suggestions about the replenishment of stock. This gave the owner and managers the opportunity to foresee their inventory needs and avoid inventory problems such as shortages and overstocking.

The information was processed further and directed to the Alert and Reporting Module. This module generated inventory status, fast-moving and slow-moving product, stock replenishment, and expiration monitoring reports, as well as low-stock alerts, overstock alerts, and near-expiration notices. The reports gave timely information regarding inventory performance and alerted the concerned users when urgent inventory action was required.

Finally, with the help of the Dashboard Module, all the processed information, analysis results, forecasts, alerts, and reports were displayed. The dashboard gave users a complete overview of the current inventory situation, stock quantities, transaction history, demand forecasts, forecast accuracy, recommendations, and various reports. Through the dashboard, the store owner and managers could easily monitor the performance of their business, evaluate inventory status, and make meaningful inventory decisions based on the information provided by the system in real time.

IntelliWare's architectural framework integrated authentication, camera-based product identification, product registration, local database management, data analytics, rule-based classification, machine learning forecasting, reporting, and dashboard visualization into a single comprehensive inventory management system. The integration of these interconnected components resulted in an inventory management system that increased inventory accuracy, enhanced the effectiveness of inventory monitoring, introduced intelligent forecasting capabilities, and provided effective decision-making support for Shea and Nicole's General Merchandise Store.

<img src="media/image1.jpg" style="width:6.00695in;height:3.37847in" /><img src="media/image2.png" style="width:0.41319in" /><img src="media/image3.png" />

*Figure 1. Architectural Framework of IntelliWare*

# Level 0 DFD 

The Level 0 Data Flow Diagram (DFD), or Context Diagram, represented the IntelliWare system as one high-level abstraction without any internal details and defined the boundaries of the IntelliWare system (i.e., the user and any external entities). By defining boundaries around the system, all users, including non-technical users such as staff and the store owner, could gain a complete understanding of the overall purpose of IntelliWare and how it fit within its external environment.

The external entity that interacted with IntelliWare in the Level 0 Data Flow Diagram was the User, who held one of three roles: (1) Administrator (Store Owner), (2) Manager, or (3) Staff. The Administrator and Manager primarily accessed the system through the web-based dashboard, providing login credentials and receiving inventory status updates, automated alerts, and analytical reports in return. Staff primarily interacted with the system through the mobile-responsive web interface on their phone's browser, providing login credentials and product or stock transaction data, including photos captured through the camera-based recognition feature, and receiving confirmation of recorded stock changes. The Level 0 DFD is shown in Figure 2.

<img src="media/image7.png" style="width:6.00694in;height:3.35694in" />

*Figure 2: Level 0 Data Flow Diagram of IntelliWare*

# Algorithm Discussion 

Product and transaction data used by the algorithms could be captured through manual entry or through the device’s camera. When a user photographed a product during registration, IntelliWare sent the image to the Google Gemini Vision multimodal API, implemented in the ai_extract_product.php script, which read the packaging and extracted the product title, brand, net weight or volume, packaging type, and suggested reorder defaults. These values pre-filled the registration form for the user to confirm or correct. This reduced data entry time and errors, and the saved record was processed the same way as manually entered data, ensuring consistency in how the analytics and classification algorithms treated inventory. Items without visible packaging or labels still had to be registered manually.

IntelliWare used three main algorithms: the Data Analytics and Pattern Analysis algorithm, the Rule-Based Classification algorithm, and the Machine Learning Forecasting algorithm. The first two were developed in PHP 8.2 and ran on the XAMPP server (Apache), working directly with the transaction records in the MySQL database, which used the InnoDB engine, UTF-8 collation, and relational foreign keys to keep product, batch, and transaction records consistent. The Forecasting algorithm was implemented in Python 3.10 or higher, using the Prophet, Scikit-learn, XGBoost, LightGBM, Pandas, and NumPy libraries, and its scripts (train_model.py and predictor.py) were kept in the system’s src/ml folder. Together, these algorithms converted raw inventory data into product classifications, trend reports, demand forecasts, expiry alerts, and stock statuses.

The data analytics algorithm first counted and totaled all sales transactions for an item during the past 30 days, which became the monthly sales figure. The script divided this figure by 30 to get the average units sold per day, referred to as the Daily Sales Velocity. The formula used was:

> Daily Sales Velocity = Monthly Sales ÷ 30

The system then calculated how long the current stock would last at that rate. The formula for the Days of Supply was:

Days of Supply = Current Stock ÷ Daily Sales Velocity

Products with a Daily Sales Velocity of 0 that had stock on hand were set to 999 days of supply, indicating that the item had no demand and that it was not possible to estimate how long the stock would last. Products with both a Daily Sales Velocity and stock equal to 0 were set to 0 days of supply.

Products were also divided into movement categories based on monthly sales volume: Fast-Moving, 50 or more units; Medium-Moving, 10 to 49 units; Slow-Moving, 1 to 9 units; and Non-Moving, no sales. Fast-Moving products needed to be restocked frequently because they tended to sell out regularly. Medium-Moving products sold at a steady rate, Slow-Moving products risked piling up in inventory, and Non-Moving products either had no sales or might need to be removed or phased out.

The Rule-Based Classification algorithm used a set of If-Then rules to classify products into four classes based on their current stock levels. The logic of the algorithm was:

> IF Current Stock ≤ 0 → Out of Stock
>
> ELSE IF Current Stock \< Reorder Point → Low Stock
>
> ELSE IF Days of Supply \> 90 → Overstock
>
> ELSE → Normal

For demand forecasting, IntelliWare applied a tiered modeling approach based on how much transaction history was available for a product. The daily aggregate demand was modeled across 7-, 14-, and 30-day forecasting horizons. When at least 60 days of history were available, the system used Meta Prophet, an additive time-series regression model, configured with weekly and yearly seasonality and with daily seasonality turned off because the data were recorded per day. For products with 7 to 59 days of history, the system used models built on lag and rolling-average sales features. The complete model hierarchy had five tiers in which each model served as a fallback for the one before it: Prophet; a tri-ensemble of XGBoost, LightGBM, and ExtraTrees; XGBoost alone; Gradient Boosting; and finally, a Random Forest Regressor. If a higher tier could not be fitted or did not suit the available data, the system automatically moved to the next tier, so that a forecast was always produced even for newer products without a full season of sales history.

Forecast accuracy was evaluated using a multi-horizon Warehouse Replenishment Weighted Absolute Percentage Error (WAPE). The error was weighted 55% on the accuracy of the 7-day replenishment cycle, 30% on total volume fulfillment, and 15% on point accuracy. These weights reflected what mattered most to the store, which was having enough stock to cover the next replenishment cycle rather than predicting each single day exactly. WAPE was used instead of the Mean Absolute Percentage Error (MAPE) because MAPE divides by the actual value of each day and cannot be computed on days with zero sales, which are common in a general merchandise store. A lower WAPE indicated a more reliable forecast.

To support the forecasts with market context, IntelliWare included the Philippine Market and Seasonal Demand Recommendations module, which had two tabs. The Macro Seasonal Forecast tab used a predefined month-by-month Philippine seasonal profile, with its events, climate drivers, and seasonal demand multipliers, to show the products and categories expected to rise in demand for the month and forecast horizon the user selected. The Gemini AI Philippine Market Pulse tab (gemini_ph_market_pulse.php) used Google Gemini to estimate real-time consumer purchasing intent for a selected date and region, based on a chosen scenario, such as the 15th and 30th payday cycles, weekend routines, rainy or hot weather, or Undas, or a custom prompt entered by the user. For both tabs, the expected demand lifts were cross-referenced with the stock on hand so that the store could see which products were at risk of running out and prepare its inventory before the peak period.

All three algorithms ran automatically when a user accessed the system or completed a stock transaction, so the store was always informed of its inventory status. When the algorithms identified products that were low on stock, overstocked, or near expiry, the system delivered alerts through on-screen dashboard notices, through email using PHPMailer over SMTP (EmailService.php), and through SMS using the Semaphore API (SmsService.php). The dashboard and mobile-responsive interface were built with HTML5, CSS3, and JavaScript (ES6+), using a shared CSS design system (root.css and mobile.css), Bootstrap, FontAwesome icons, and flexible grid layouts, so that Administrators, Managers, and Staff could view the same results on both computers and phones.

# Software Engineering (SE) Paradigm 

IntelliWare was developed using Agile Software Development, characterized by developing projects in iterative phases called sprints. Agile involved creating the complete system in small increments, allowing the team to hold regular meetings with the store owner and store staff to gather contributions, requests, and feedback about the progress of the system's development.

This approach helped distribute the development work of a project such as IntelliWare, as there was a lot of variability in user behavior regarding the different elements of the system, including classification rules, alert settings, and displays.

Furthermore, the Scrum framework broke down all expected functionalities into a series of sprints, each of which consisted of planning and review meetings. During the meetings, the functionalities to be delivered by the end of the sprint and their priorities were identified using the MoSCoW method (Must-Have, Should-Have, Could-Have, and Won't-Have) so that the developers focused first on the most important features. The goals of the sprints were as follows:

In Sprint 1, the infrastructure of the system and the user login processes were developed, and in Sprint 2, the database for inventory and fundamental product management was developed. During Sprint 3, the data analytics and rule-based classification modules were developed, including methods of tracking expiry dates, and in Sprint 4, all system components such as the dashboard, reporting functionality, and automated alerts were developed.

Furthermore, the researchers conducted brief daily meetings to monitor progress, share information, and overcome obstacles. This helped keep the development on schedule and clear up issues immediately. The use of Agile techniques ensured the prompt creation of an operational system with fewer problems than would have been possible with a more conventional method of development.

# Population and Sampling 

The population of participants for this study included the store owner and the employees of Shea and Nicole's General Merchandise Store who were responsible for managing and monitoring the store's inventory. They were the primary users of IntelliWare and were directly involved in the inventory management process. In addition, ten (10) IT professionals evaluated the functionality, usability, and overall performance of the system according to predefined software quality standards. The number of ten (10) IT professional evaluators was chosen because a group of this size is generally considered sufficient in software evaluation practice to surface the majority of functionality, usability, and reliability issues in a system of this scope, while remaining practical to coordinate given the time and resource constraints of the study. The number was also set based on the recommendation of the researchers' adviser, who advised that it would be adequate to gather sufficiently diverse and reliable technical feedback on the system given the scope and timeline of the study.

The researchers used purposive sampling as their sampling method. This method was appropriate because the researchers could select individuals with the experience and expertise in inventory management and information technology that were relevant to the study. The researchers selected the owner and employees of the store based on their daily involvement in the store's inventory management and the IT professionals based on their education and experience in developing systems, evaluating software, or similar fields.

The study involved fourteen (14) participants: the store owner, three (3) employees of the store, and ten (10) IT professionals. The store owner and employees provided data on the existing inventory management process and helped identify the store's operational requirements, which ensured that IntelliWare met the necessary requirements of the store, and they also took part in evaluating the system. Meanwhile, the IT professionals evaluated the technical quality, functionality, reliability, and usability of the system. After their evaluations, the IT professionals provided recommendations to improve the developed inventory management system.

# Data Collection Methods 

Data collection for this study was done using three methods: semi-structured interviews, direct observation, and a survey utilizing a questionnaire based on the ISO/IEC 25010:2023 Software Quality Standard.

The primary sources of information obtained by the researchers regarding current handling of inventory at the store were semi-structured interviews and direct observations as these provided the researchers with first-hand accounts from both the store owner and the employees regarding their experiences, and the researchers were able to observe how inventory was handled daily.

The researchers interviewed the store owner and employees to assess the current inventory process and identify the challenges they faced. The researchers prepared questions for guided interviews but allowed time for further exploration of useful topics that came up during the interviews. The interviews focused on five topic areas: how the store recorded and monitored its inventory, what challenges it had, how it maintained sales and product usage records, the total number of products managed by the store, and the greatest benefit a new system could provide.

During multiple visits to the store, the researchers were able to observe how inventory-related tasks were conducted, such as receiving shipments, placing stock in storage, and updating sales records. The visits allowed the researchers to identify additional findings that may not have been discussed during the interviews, including scattered records and unlabeled products. The researchers kept notes of the processes and documentation (e.g., notebooks, spreadsheet applications) used to complete the tasks during each visit.

The requirements for IntelliWare, both functional and non-functional, were defined from the data collected during the interviews and observations. Functional requirements described what the system needed to do, including inventory recordkeeping, generating low-stock and near-expiry alerts, creating reports, performing data analytics, and managing user access. Non-functional requirements described how well the system needed to perform (i.e., usability, reliability, accuracy, and security).

An evaluation of IntelliWare using a questionnaire was performed after the completion of the system. The questionnaire was adapted from the ISO/IEC 25010:2023 Software Quality Model and covered six quality characteristics: Functionality, Reliability, Usability, Efficiency, Portability, and Maintainability. Security was assessed through an item on unauthorized access under Functionality, and Compatibility was not included in this evaluation. Each of the fourteen participants rated each item on a four-point scale (4 – Strongly Agree, 3 – Agree, 2 – Disagree, 1 – Strongly Disagree) and was given the opportunity to provide written comments. The questionnaire was distributed to all fourteen participants through a Google Forms link.

# Data Analysis Procedures 

The data collected from the questionnaire were analyzed using descriptive statistics. The overall ratings based on the respondents' answers to the Likert-type questionnaire were calculated using the weighted mean for each quality characteristic.

The formula used is:

<img src="media/image8.jpg" style="width:2.65833in;height:0.825in" />

Where:

- f = frequency of responses

- x = scale value

- N = total number of responses

The results were interpreted using the following scale:

**Table 1:**

**Likert-Scale Interpretation**

| **Range**   | **Interpretation** |
|-------------|--------------------|
| 3.26 – 4.00 | Strongly Agree     |
| 2.51 – 3.25 | Agree              |
| 1.76 – 2.50 | Disagree           |
| 1.00 – 1.75 | Strongly Disagree  |

The overall weighted mean of the system was computed as the mean of the weighted means of all fifteen (15) items, so that each quality characteristic was weighted by its number of items.

# Ethical Considerations 

The researchers followed ethical guidelines and standards to ensure everyone's rights, privacy, and safety during the research study.

The researchers received consent from the store owner and all employees prior to collecting any data. They informed each participant of the study's purpose, their role in the study, how their data would be used, and their right to withdraw at any time with no negative impact. There was no coercion or pressure on any of the participants to give consent.

The participants' privacy was always protected during the study. The personal information collected by the research team was used only for the study and was not shared with individuals who were not part of the research study. All store-specific data such as inventory records were handled in a confidential manner and were not made available to the public. At the conclusion of the study, the research team secured all data collected because of the study and properly disposed of it.

The researchers were also open and honest in their relationship with the participants during the entirety of the research project; therefore, the collected data were accurate records of how the participants had represented their inputs. The researchers provided the participants with updates on the progress of the study and an opportunity to check the system specifications based on their answers to the survey questions.

IntelliWare was built for the benefit of its users. IntelliWare did not collect information about the customers of the store; rather, its processes were set up to control access to sensitive information through a role-based method of access. This demonstrated the researchers' desire to conduct a responsible research study and to treat all participants with respect.

# Summary 

This chapter presented the various approaches employed to develop IntelliWare for Shea and Nicole's General Merchandise Store. With the purpose of creating a usable system, the project used a developmental research design since there were no natural conditions in which to conduct this type of research. The Scrum method was selected to guide the development process because it permitted the team to develop in phases (iterations) and allowed continual implementation of the feedback received from the owner and employees of the store.

IntelliWare's architectural structure linked all parts of the system; users entered data, and the resulting reports and alerts were used to manage the store's inventory as one continuous process. The three main algorithms in IntelliWare, the Data Analytics and Pattern Analysis algorithm, the Rule-Based Classification algorithm, and the Machine Learning Forecasting algorithm, produced information about classified products, demand forecasts, and real-time updates on stock levels and expiration date status.

Interviews, observation, and an evaluation questionnaire based on the ISO/IEC 25010:2023 standard were used to collect data. The researchers then calculated the overall quality of the system by means of a weighted mean analysis of the collected data. In addition, the researchers maintained ethical standards during the entire study, ensuring that the privacy and rights of all individuals involved were preserved. All these methods were used to establish that IntelliWare was a viable, dependable, and beneficial solution to the inventory management problems faced by Shea and Nicole's General Merchandise Store.

**CHAPTER 3**

**Results and Discussions**

This chapter presents the results from testing and evaluating IntelliWare, a data-driven inventory management system that uses data analytics and rule-based classification to support decision-making at Shea and Nicole's General Merchandise Store. It describes the system's capabilities, the testing process, and the data gathered from the System Evaluation Tool. Results are organized around the Statement of the Problem, covering record accuracy, stock-level monitoring, analytics and classification integration, decision support, reorder alerts, seasonal demand identification, and overall acceptability based on ISO/IEC 25010:2023. <span class="mark"></span>

# Project Capabilities and Testing Process Details 

IntelliWare was built as nine connected modules that worked on one shared MySQL database. A user first logged in through the Authentication and Access Control Module, which determined the functions that the user could open. Products were then registered manually or through the camera, and every record and transaction was saved in the database. The Data Analytics Engine and the Rule-Based Classification Engine processed these records to classify products and detect stock problems, the Machine Learning Forecasting Module projected future demand, and the Alert and Reporting Module and the Dashboard Module presented the results to the owner, managers, and staff.

The database was hosted locally on the store's XAMPP server, and the web dashboard and the mobile-responsive interface read and updated the inventory information in real time if the device was connected to the store's local network. Administrators and Managers mainly used the web-based dashboard, while Staff used the mobile-responsive interface on their phone's browser to record stock transactions and to register products with the camera.

**Authentication and Access Control Module**

The module was the entry point of every session. Users logged in with their username or email address and password, which the system checked against the user records in the central database; matching credentials opened the dashboard for the user's role (Administrator, Manager, or Staff), while invalid credentials were rejected and no inventory data was shown. Role-Based Access Control (RBAC) attached permissions to the role, so Administrators had full access while Managers and Staff could use only the functions that matched their responsibilities. To maintain security while preventing administrative lockouts during off-hours, the module integrated a self-service Password Recovery mechanism When a user could not recall their login credentials, the system initiated an automated three-step identity verification process.

**Figure 3:**

<img src="media/image9.png" style="width:6.28472in;height:3.15417in" />**IntelliWare Login Page**

Figure 3 presents the IntelliWare login page, which was the entry point of the system. It contained the fields for the username or email address and the password, a link for password recovery, and the Login button. A user who supplied valid credentials was directed to the dashboard that matched his or her role, while invalid credentials were rejected and no inventory data was displayed.

**Figure 3.1:**

<img src="media/image10.png" style="width:6.28472in;height:3.17986in" />**Forgot Password**

Figure 3.1 presents the Forgot Password page, which was reached through the password recovery link on the login page. It guided a user who could not recall his or her credentials through the automated three-step identity verification process before access to the account was restored. This allowed users to recover their accounts on their own, even during off-hours, without waiting for an administrator.

**Product Registration and Master Catalog Module**

The module functioned as the central repository where inventory records were created, categorized, and cataloged. It provided store owners and authorized personnel with a comprehensive Master Inventory Catalog equipped with real-time keyword search, category filtering, stock health counters, and view switches between an image-based card grid and a tabular audit view. To add products, the module offered both manual keyboard entry and camera-based recognition. In manual entry, users typed the product details, with the reorder point preset to 10 units and stock recorded by batch (batch number, manufacturing date, and expiry date) for First-Expired, First-Out release. In camera-based registration, a photo was matched by the trained recognition model or, for products outside that set, read by Google Gemini Vision to pre-fill the name, brand, category, unit of measure, and description. Users then reviewed the details and saved the record to the MySQL database, where camera data and manual data were processed the same way.

**Figure 4:**

**Master Inventory**

<img src="media/image11.png" style="width:6.28472in;height:3.17014in" />

Figure 4 presents the Master Inventory Catalog, the central list of all registered products. It provided real-time keyword search, category filtering, and stock health counters at the top of the page, together with a switch between the image-based card grid and the tabular audit view. This allowed authorized users to find a product and check its stock condition without going through all the records.

## Figure 4.1: 

## Add New Product 

<img src="media/image12.jpg" style="width:2.67708in;height:3.06528in" /><img src="media/image13.jpg" style="width:2.65625in;height:3.07361in" />

Figure 4.1 presents the Add New Product form used for manual entry. The upload and take-photo options appeared at the top of the form, followed by the fields described above. The reorder point was preset and could be changed per product, since this value was later used by the rule-based classification to trigger low-stock alerts. The Save Product button recorded the product in the database, and the form could also be completed using the keyboard shortcuts displayed at the bottom.

## Figure 4.2 

## <img src="media/image14.jpg" style="width:2.975in;height:3.525in" />Sample Added Product (Gemini Vision analysing packaging)

Figure 4.2 presents a sample product being added through the camera-based feature. After the photo of the product was selected, the system displayed a notice that Google Gemini Vision was analyzing the packaging. The user could retake the photo if the image was unclear. Once the analysis was completed, the recognized details were placed in the corresponding fields of the form, and the user reviewed them before saving the product.

**Daily Inventory Transactions and Batch Tracking Module**

The module served as the primary operational engine for recording physical stock movements within the store, replacing the traditional handwritten transaction logs and manual tally sheets. It was divided into three dedicated interfaces: Inbound Stock Receiving (receive.php), Outbound Stock Releasing (release.php), and Transaction History (inventory_history.php). When deliveries arrived from suppliers, staff used the receiving interface to log incoming quantities alongside critical batch metadata, including batch numbers, manufacturing dates, and expiration dates. For outbound movements, the releasing interface facilitated rapid stock dispensing for daily counter sales, enforcing automated limiter checks that prevented staff from deducting quantities beyond available on-hand stock, thereby eliminating negative balances.

**Figure 5:**

**Stock Transactions Page**

<img src="media/image15.png" style="width:6.28472in;height:3.18333in" />

Figure 5 presents the Stock Transactions page, which served as the starting point for daily stock movements. It gave access to the three interfaces of the module: Inbound Stock Receiving, Outbound Stock Releasing, and Transaction History. Staff used this page to choose whether to record a delivery, release stock, or review past transactions.

**Figure 5.1:**

**Stock Receiving (with sample product to receive)**

<img src="media/image16.png" style="width:6.28472in;height:3.17014in" />

Figure 5.1 presents the Stock Receiving interface with a sample product selected for receiving. Staff entered the incoming quantity together with the batch number, manufacturing date, and expiry date of the delivery. Each delivery was recorded as its own batch so that First-Expired, First-Out release and expiration alerts could work on it later.

**Figure 5.2:**

**Stock Releasing (with sample product to release)**

<img src="media/image17.png" style="width:6.28472in;height:3.17014in" />

Figure 5.2 presents the Stock Releasing interface with a sample product selected for release. Staff entered the quantity to be released for a counter sale, and the system checked it against the available on-hand stock so that quantities beyond the stock could not be deducted. Stock was deducted from the batch with the nearest expiry date, following the First-Expired, First-Out rule.

**Figure 5.3:**

**Inventory Transaction History**

<img src="media/image18.png" style="width:6.28472in;height:3.19653in" />

Figure 5.3 presents the Inventory Transaction History, which listed the stock-in and stock-out transactions recorded in the system. Each entry showed the product, quantity, and date of the movement. This replaced the handwritten transaction logs and served as the record used by the analytics engine.

**Data Analytics Engine**

The engine, written in PHP, worked directly on the transaction records in the MySQL database and ran automatically whenever a user accessed the system or completed a transaction. It totaled each product's sales over the previous 30 days, divided this by 30 to get the Daily Sales Velocity, and divided current stock by the velocity to get the Days of Supply (999 for products with stock but no sales, and 0 for no sales and no stock). The results were summarized by product, category, sales trend, supplier, and expiry, served as the basis of the Rule-Based Classification Engine and the Forecasting Module, and were shown in the Data Mining and Analytics page.

**Figure 6:**

**Data Analytics**

<img src="media/image19.jpg" style="width:5.39653in;height:3.025in" />

Figure 6 presents the Data Mining and Analytics page, which served as the main view of the Data Analytics Engine. The summary cards at the top showed the total number of products, the number of low-stock and out-of-stock products, the number of fast-moving products, and the number of products expiring soon. Below the cards, the tabs for Product Analysis, Category Performance, Sales Trends, Supplier Analysis, and Expiry Alerts allowed the user to move between the different analytic views.

**Figure 6.1:**

<img src="media/image20.jpg" style="width:5.50764in;height:2.71667in" />**Product Movement Analysis**

Figure 6.1 presents the Product Movement Analysis table. Each product was listed with its category, current stock, monthly sales, daily velocity, days of supply, movement class, and stock status. The user could search for a product by name and filter the list by category, movement type, and stock status, which made it possible to find slow-selling or low-stock items without going through all the records.

**Figure 6.2:**

<img src="media/image21.jpg" style="width:5.90278in;height:2.4in" />**Category Performance**

Figure 6.2 presents the Category Performance view. The table summarized each product category by the number of products, total stock, average stock, the number of low-stock items, and sales, while the Sales Distribution graph beside it showed how sales were spread across the categories. This allowed the owner to see which categories carried the most stock and which ones were selling.

**Figure 6.3:**

<img src="media/image22.jpg" style="width:6.04861in;height:1.81667in" />**Sales Trends**

Figure 6.3 presents the Sales Trends view for the last 30 days. The table listed the date, day, number of products sold, total quantity, and average quantity per transaction, and the Trend Visualization plotted the same data over time. The view showed how the store’s sales changed from day to day.

## Figure 6.4:

## <img src="media/image23.jpg" style="width:5.25in;height:2.2in" />Supplier Performance 

Figure 6.4 presents the Supplier Performance view. Suppliers were listed with the number of products supplied, total stock, average stock, monthly sales, the number of low-stock items, and a status indicator. The view helped the store identify which suppliers provided products that were running low and needed to be reordered.

**Rule-Based Classification Engine**

The engine applied predefined If-Then rules to the analytics results so that each product was classified automatically. By monthly sales, products were Fast-Moving (50 or more units), Medium-Moving (10 to 49), Slow-Moving (1 to 9), or Non-Moving (no sales). Stock status was checked in a fixed order: Out of Stock (stock of zero or less), Low Stock (below the reorder point), Overstock (Days of Supply above 90), and Normal otherwise, with Low Stock triggering a reorder alert. Products expiring within 30 days were flagged and given a risk level based on the days left and the rate of sales, and First-Expired, First-Out deduction kept these alerts aligned with the batches at risk.

**Figure 7:**

<img src="media/image24.jpg" style="width:5.825in;height:1.81667in" />**Product Movement Analysis (Filtered with stock statuses)**

Figure 7 presents the Product Movement Analysis page filtered by movement type and stock status. In the sample shown, the Fast-Moving filter was selected, and the table listed only the products that met this classification, together with the stock status assigned by the rules. The drop-down menus showed the four movement classes that the user could choose from.

## Figure 7.1:

## <img src="media/image25.jpg" style="width:5.98333in;height:2.45833in" />Expiry Risk Analysis 

Figure 7.1 presents the Expiry Risk Analysis. Each product with an expiration date was listed with its stock, expiry date, days left, sales, sell-through, and a risk level. The risk level was determined from the days left before expiry and the rate at which the product was selling, so that items that were unlikely to be sold before they expired could be discounted, returned to the supplier, or removed from the shelf in time.

**Machine Learning Forecasting Module**

The module, written in Python, projected future demand from historical sales and inventory data using a tiered approach. Meta Prophet was used for products with at least 60 days of history, while products with 7 to 59 days used a five-tier fallback of Prophet, an XGBoost, LightGBM, and ExtraTrees tri-ensemble, XGBoost, Gradient Boosting, and a Random Forest Regressor, built on lag and rolling-window sales features. Accuracy was measured using a multi-horizon Warehouse Replenishment Weighted Absolute Percentage Error (WAPE), weighted 55% on the 7-day replenishment cycle, 30% on total volume fulfillment, and 15% on point accuracy, which avoids division by zero on days with no sales. The projected 30-day demand was compared with the stock on hand to recommend actions such as an urgent restock or a pause in purchase orders.

**Figure 8:**

**30-Day Inventory Replenishment & Demand Forecast table**

<img src="media/image26.png" style="width:6.28472in;height:2.7375in" />

Figure 8 presents the 30-Day Inventory Replenishment and Demand Forecast table. For each of the top in-demand products, the table showed the current stock, the seasonal demand and stock intelligence (including the projected demand for the period and the number of days the stock was expected to last), and the recommended action, such as placing an urgent restock order for products that were about to run out or pausing new purchase orders for products with excess stock. The Check Stock button led the user to the record of the product concerned.

**Figure 8.1:**

**Philippine Market and Seasonal Demand Recommendations, Macro Seasonal Forecast Tab**

<img src="media/image27.png" style="width:5.92705in;height:2.81093in" />

Figure 8.1 presents the Macro Seasonal Forecast, the first tab of the Philippine Market and Seasonal Demand Recommendations pop-up. The user selected the Forecast Horizon and the Target Month, and the pop-up displayed the seasonal period for that month, its climate note, and the Consumption Triggers expected to drive demand. In the sample shown, October was labeled Oktoberfest / Pre-Undas, with triggers such as Oktoberfest events, Halloween parties, Undas preparations, and early holiday bonuses. Below these, the Seasonal Demand and Stock Alignment table, cross-referenced with real-time inventory, listed each in-demand product with its expected surge, recommendation, and stock on hand. For example, Red Horse Beer was expected to surge by 70%, with 203 units in stock, and the Check Stock button opened its product record.

**Figure 8.2:**

**Philippine Market and Seasonal Demand Recommendations, Macro Seasonal Forecast Tab**

<img src="media/image28.png" style="width:5.92862in;height:2.80709in" />

Figure 8.2 presents the Gemini AI Philippine Market Pulse, the second tab of the same pop-up, marked as Live AI. This tab used Google Gemini to analyze real-time consumer purchasing intent for a chosen day. The user selected a scenario, such as Payday Quincena, TGIF Friday Inuman, Saturday Grocery Run, or Undas / All Saints’ Day, or typed a custom prompt, then chose a date and region and pressed Analyze Wants. In the sample shown, for Saturday, October 17, 2026, the tab displayed a summary of the shopping behavior, a Moderate demand level, and the surging categories, Bakery Products and Snacks (+45%) and Canned Goods and Pantry (+30%). The What Filipinos Are Buying and Craving Right Now section then listed the specific products analyzed, such as Gardenia White Bread (+50%) and Purefoods Tender Juicy Hotdog (+60%), each with its category, target households, and reason for demand, cross-referenced with the warehouse inventory.

**Alert and Reporting Module**

The module alerted users to inventory problems and produced the reports they needed, using the analytics and classification results and running automatically whenever a user accessed the system or completed a transaction. A notification showing the alert type, product name, quantity remaining, and reorder point was created when a product fell below its reorder point, became overstocked, or neared its expiration date. For reports, users chose a date range, category, and stock status to generate the inventory status, fast-moving and slow-moving product, stock replenishment, and expiration monitoring reports, which could be exported, including the full Official Inventory and Audit Report.

## Figure 9: 

## <img src="media/image29.png" style="width:2.16042in;height:3.09097in" />Stock Notifications 

<img src="media/image30.png" style="width:4.03681in;height:3.11111in" />

Figure 9 presents the Stock Notifications panel. Each notification showed the alert type, the name of the product, the quantity remaining, and the reorder point that triggered the alert. In the sample shown, two products had fallen below their reorder points and were flagged as Low Stock. The Refresh option updated the list with the latest stock status.

**Figure 9.1:**

**Reports Page**

<img src="media/image31.png" style="width:6.28472in;height:3.17986in" />

Figure 9.1 presents the Reports page. The user set the date range, category, and stock status in the Generate Report section and pressed the search button. The summary cards then showed the number of products, the total units, the low-stock count, and the out-of-stock count for the selected filters, and the Stock Explorer listed the products by category with options to export the results.

**Figure 9.2:**

**Sample Generated Report**

> <img src="media/image32.jpg" style="width:4.10347in;height:3.70139in" />

Figure 9.2 presents a sample generated report, the Official Inventory and Audit Report. The report header displayed the reference number, the user who generated the report, the report scope and parameters, and the inventory reconciliation. The summary boxes showed the totals for products, units in the warehouse, good stock, low stock, and out of stock, and the table below listed each product with its SKU, category, stock quantity, unit, reorder point, and health status.

**Dashboard Module**

The module was the last stage of the system, collecting the analysis results, forecasts, alerts, and reports into one view that depended on the role of the logged-in user. The Administrator dashboard showed a critical-stock warning, summary figures (total SKUs, total items, low-stock alerts, and active categories), recent stock movements, critical stock levels, top-demand products, and shortcuts for receiving and releasing inventory. The Manager dashboard used the same layout as the Administrator dashboard but hid the User Management and System Settings functions, which remained available only to the Administrator. The Staff dashboard was simpler, showing the total products, total quantity in stock, Stock In and Stock Out shortcuts, low-stock alerts, and stock distribution by category, with the administrative menus hidden.

## Figure 10: 

## Admin Dashboard 

<img src="media/image33.png" style="width:6.28472in;height:3.18681in" />

<img src="media/image34.png" style="width:6.28472in;height:3.18681in" />

Figure 10 presents the Admin Dashboard. A banner at the top notifies the user of products that were critically low, and the summary cards showed the total SKUs, total items, low-stock alerts, and active categories. The Quick Access section allowed the user to receive or release inventory, the Recent Activity section listed the latest stock movements, the Critical Stock Levels section showed the products at or below their reorder points, and the Top Demand chart showed the products with the highest demand.

**Figure 10.1:**

**Manager Dashboard**

<img src="media/image35.png" style="width:6.28472in;height:3.18681in" />

Figure 10.1 presents the Manager Dashboard, it used the same card layout as the Administrator dashboard, with a role badge identifying the logged-in user as Manager, and it summarized the stock figures, alerts, and product movement that the manager needed to monitor inventory and plan restocking. The User Management and System Settings menus were hidden from the sidebar, so the manager could not manage accounts, configure the notification gateways and AI scanner credentials, or download database backups.

**Figure 10.2:**

**Staff Dashboard**

<img src="media/image36.png" style="width:6.28472in;height:3.16389in" />

Figure 10.2 presents the Staff Dashboard. It showed the total number of products, the total quantity in stock, and the status of the logged-in staff member. The Quick Actions section provided the Stock In, Stock Out, and View Full Inventory buttons, the Low Stock Alerts section listed the products that needed to be restocked, and the Inventory Distribution chart showed how the stock was spread across the product categories. Menu items for administrative functions such as user management and reports were not available to the staff account.

**System Governance and Administration Module**

The module provided centralized administrative control, security governance, and operational configurability for the store owner. It comprised User Account Management, the System Audit Trail, and System Settings. Through the user management interface, administrators created employee accounts, assigned role privileges (Administrator, Manager, or Staff), and initiated password resets, backed by safety constraints preventing accidental self-deletion of the active administrative account.

**Figure 11:**

**User Management**

<img src="media/image37.png" style="width:6.28472in;height:3.16736in" />

Figure 11 presents the User Management page of the System Governance and Administration Module. The administrator used it to create employee accounts, assign a role (Administrator, Manager, or Staff), and initiate password resets. A safety constraint prevented the active administrator account from being deleted by accident.

**Figure 11.1:**

**System Audit Trail**

<img src="media/image38.png" style="width:6.28472in;height:3.19028in" />

<img src="media/image39.png" style="width:6.28472in;height:3.17014in" />

Figure 11.1 presents the System Audit Trail, which kept a record of the actions performed by users in the system. Each entry identified the user and the action taken so that every operation could be traced to the person who performed it. This supported the accountability and security of the store’s inventory data.

**Figure 11.2:**

<img src="media/image40.png" style="width:6.28472in;height:3.49375in" />**System Settings – General & Rules**

Figure 11.2 presents the General and Rules tab of the System Settings. It allowed the administrator to configure the general store information and the rules used by the system, such as the reorder point and the thresholds used in classifying products.

**Figure 11.3:**

<img src="media/image41.png" style="width:6.28472in;height:3.68472in" />**System Settings – Notifications and Gateways**

Figure 11.3 presents the Notifications and Gateways tab of the System Settings. It was used to set up how replenishment and stock alerts were delivered, namely through on-screen dashboard alerts, email, and SMS. The administrator could configure the email and SMS gateways here.

**Figure 11.4:**

**System Settings – AI Packaging Scanner**

<img src="media/image42.png" style="width:6.28472in;height:3.57917in" />

Figure 11.4 presents the AI Packaging Scanner tab of the System Settings. It showed the settings for the Google Gemini Vision feature that reads product packaging during camera-based registration.

**Figure 11.5:**

<img src="media/image43.png" style="width:6.28472in;height:2.57917in" />**System Settings – AI Demand Forecasting**

Figure 11.5 presents the AI Demand Forecasting tab of the System Settings. It showed the settings for the Machine Learning Forecasting Module, including the forecasting approach and the history requirements used for Meta Prophet and the fallback models.

**Figure 11.6:**

<img src="media/image44.png" style="width:6.28472in;height:2.6625in" />**System Settings – Maintenance and Backups**

Figure 11.6 presents the Maintenance and Backups tab of the System Settings. It allowed the administrator to back up the MySQL database and carry out routine maintenance of the system. These backups protected the inventory and transaction records against data loss.

**Test Plan and Test Data**

A test plan was prepared to outline the strategy, objectives, resources, schedule, and success criteria used to test IntelliWare, consistent with the functional and non-functional requirements identified in Chapter 2 and the Statement of the Problem in Chapter 1. Testing had two parts: (1) functional testing of each module using a prepared test data set, and (2) evaluation by fourteen (14) respondents using the System Evaluation Tool. Prior to testing, the researchers populated the system with 400 product records and over 400 transaction records so that the Data Analytics Engine, Rule-Based Classification Engine, and Forecasting Module would have sufficient data to produce meaningful output.

**System Evaluation Instrument**

Following functional testing, IntelliWare was evaluated by fourteen (14) respondents, the store owner, three (3) store employees, and ten (10) IT professionals, using a System Evaluation Tool adapted from the ISO/IEC 25010:2023 Software Quality Model. The instrument contained fifteen (15) items grouped under six quality characteristics: Functionality (3 items), Reliability (2), Usability (4), Efficiency (2), Portability (2), and Maintainability (2). Each item was rated on a four-point scale (1 to 4), and respondents were given space to provide written comments.

**Table 2. System Evaluation Instrument**

<table>
<colgroup>
<col style="width: 25%" />
<col style="width: 41%" />
<col style="width: 33%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>Quality Characteristic</strong></th>
<th><blockquote>
<p><strong>Explanation</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Statement</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td><strong>Functionality</strong></td>
<td>The degree to which the system provides functions that meet the needs of its users, including performing the required tasks, producing the expected results, and protecting the system from unauthorized access.</td>
<td><p>1. The software can perform the tasks required.</p>
<p>2. The result is as expected.</p>
<p>3. The software prevents unauthorized access.</p></td>
</tr>
<tr class="even">
<td><strong>Reliability</strong></td>
<td>The degree to which the system performs its functions consistently, handles errors, and resumes operation and restores its data after a failure.</td>
<td><p>1. The software can handle errors.</p>
<p>2. The software can resume working and restore lost data after failure.</p></td>
</tr>
<tr class="odd">
<td><strong>Usability</strong></td>
<td>The degree to which the system can be understood, learned, and used with little effort, and whether its interface is pleasing to its users.</td>
<td><p>1. The user comprehends how to use the system easily.</p>
<p>2. The user can learn to use the system easily.</p>
<p>3. The user can use the system without much effort.</p></td>
</tr>
<tr class="even">
<td><strong>Efficiency</strong></td>
<td>The degree to which the system responds quickly and makes good use of resources when performing its functions.</td>
<td><p>1. The system responds quickly.</p>
<p>2. The system utilizes resources efficiently.</p></td>
</tr>
<tr class="odd">
<td><strong>Portability</strong></td>
<td>The degree to which the system can be installed in its environment and can replace other software used for the same purpose.</td>
<td><p>1. The software can be installed easily.</p>
<p>2. The software can easily replace other software.</p></td>
</tr>
<tr class="even">
<td><strong>Maintainability</strong></td>
<td>The degree to which faults in the system can be diagnosed and the system can be tested so that it can be corrected and improved.</td>
<td><p>1. Faults can be easily diagnosed.</p>
<p>2. The software can be tested easily.</p></td>
</tr>
</tbody>
</table>

# Presentation and Interpretation of Results:

# Table 3. Scale of Weighted Mean and Verbal Interpretation

<table>
<colgroup>
<col style="width: 50%" />
<col style="width: 49%" />
</colgroup>
<thead>
<tr class="header">
<th><blockquote>
<p><strong>Weighted Mean Average</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td><blockquote>
<p>3.26 – 4.00</p>
</blockquote></td>
<td>Strongly Agree</td>
</tr>
<tr class="even">
<td><blockquote>
<p>2.51 – 3.25</p>
</blockquote></td>
<td>Agree</td>
</tr>
<tr class="odd">
<td><blockquote>
<p>1.76 – 2.50</p>
</blockquote></td>
<td>Disagree</td>
</tr>
<tr class="even">
<td><blockquote>
<p>1.00 – 1.75</p>
</blockquote></td>
<td>Strongly Disagree</td>
</tr>
</tbody>
</table>

Table 3 presented the scale used to interpret the weighted means. A mean from 3.26 to 4.00 was interpreted as Strongly Agree, 2.51 to 3.25 as Agree, 1.76 to 2.50 as Disagree, and 1.00 to 1.75 as Strongly Disagree. In the succeeding tables, the Total Weight was the sum of the ratings given to an item by a group, and the Weighted Mean was the Total Weight divided by the number of respondents in that group. The Category Weighted Mean was the total of the item weights divided by the number of respondents multiplied by the number of items in the category.

# Table 4. Distribution of Respondents According to Profile 

<table>
<colgroup>
<col style="width: 34%" />
<col style="width: 10%" />
<col style="width: 26%" />
<col style="width: 28%" />
</colgroup>
<thead>
<tr class="header">
<th><blockquote>
<p><strong>Respondent Type</strong></p>
</blockquote></th>
<th></th>
<th><strong>Frequency</strong></th>
<th><blockquote>
<p><strong>Percentage</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td><blockquote>
<p>Owner and Staff</p>
</blockquote></td>
<td><blockquote>
<p>4</p>
</blockquote></td>
<td></td>
<td><blockquote>
<p>28.57%</p>
</blockquote></td>
</tr>
<tr class="even">
<td><blockquote>
<p>IT Professionals</p>
</blockquote></td>
<td><blockquote>
<p>10</p>
</blockquote></td>
<td></td>
<td><blockquote>
<p>71.43%</p>
</blockquote></td>
</tr>
<tr class="odd">
<td><blockquote>
<p>Total</p>
</blockquote></td>
<td><blockquote>
<p>14</p>
</blockquote></td>
<td></td>
<td><blockquote>
<p>100%</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 4 presented the profile of the fourteen (14) respondents. The owner and staff group had four (4) respondents, or 28.57% of the total. It consisted of the store owner (7.14%), who used the system as its administrator to monitor inventory and decide on restocking, and three (3) store employees (21.43%), who used the system in their daily work to record stock transactions and register products. The IT professionals’ group had ten (10) respondents, or 71.43% of the total, who evaluated the system from a technical point of view

# Functionality 

**Table 5. Weighted Means for Functionality**

<table style="width:100%;">
<colgroup>
<col style="width: 21%" />
<col style="width: 17%" />
<col style="width: 19%" />
<col style="width: 17%" />
<col style="width: 23%" />
</colgroup>
<thead>
<tr class="header">
<th><blockquote>
<p><strong>Item</strong></p>
</blockquote></th>
<th><strong>Weighted Mean of IT</strong></th>
<th><blockquote>
<p><strong>Weighted Mean of Client</strong></p>
</blockquote></th>
<th><p><strong>Overall</strong></p>
<p><strong>Weighted Mean</strong></p></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>The software can perform the tasks required.</td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.75</p>
</blockquote></td>
<td><blockquote>
<p>3.57</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The result is as expected.</td>
<td><blockquote>
<p>3.80</p>
</blockquote></td>
<td><blockquote>
<p>4.00</p>
</blockquote></td>
<td><blockquote>
<p>3.86</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td>The software prevents unauthorized access.</td>
<td><blockquote>
<p>3.60</p>
</blockquote></td>
<td><blockquote>
<p>4.00</p>
</blockquote></td>
<td><blockquote>
<p>3.71</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td><strong>Weighted Mean of Functionality</strong></td>
<td><blockquote>
<p>3.63</p>
</blockquote></td>
<td><blockquote>
<p>3.92</p>
</blockquote></td>
<td><blockquote>
<p>3.71</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 5 presented the ratings for Functionality. The item "Is the result as expected?" obtained the highest overall weighted mean (3.86; total weight 54), followed by "Does the software prevent unauthorized access?" (3.71) and "Can the software perform the tasks required?" (3.57). The owner and staff gave a category weighted mean of 3.92, which was higher than the 3.63 given by the IT professionals, and both groups gave Strongly Agree ratings. The category weighted mean was 3.71, interpreted as Strongly Agree, and no respondent rated any item Disagree or Strongly Disagree.

# Reliability 

**Table 6. Weighted Means for Reliability**

<table>
<colgroup>
<col style="width: 18%" />
<col style="width: 20%" />
<col style="width: 20%" />
<col style="width: 16%" />
<col style="width: 23%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>Item</strong></th>
<th><blockquote>
<p><strong>Weighted Mean of IT</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Weighted Mean of Client</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Overall Weighted Mean</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>The software is capable of handling errors.</td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The software can resume working and restore lost data after failure.</td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.75</p>
</blockquote></td>
<td><blockquote>
<p>3.57</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td><strong>Weighted Mean of Reliability</strong></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.63</p>
</blockquote></td>
<td><blockquote>
<p>3.54</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 6 presented the ratings for Reliability. The item on handling errors obtained an overall weighted mean of 3.50, and the item on resuming work and restoring lost data after a failure obtained 3.57. The IT professionals rated both items 3.50, while the owner and staff rated them 3.50 and 3.75. The category weighted mean was 3.54, interpreted as Strongly Agree, and no respondent rated any item Disagree or Strongly Disagree.

1.  **Usability**

**Table 7. Weighted Means for Usability**

<table>
<colgroup>
<col style="width: 19%" />
<col style="width: 20%" />
<col style="width: 20%" />
<col style="width: 16%" />
<col style="width: 22%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>Item</strong></th>
<th><blockquote>
<p><strong>Weighted Mean of IT</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Weighted Mean of Client</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Overall Weighted Mean</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>The user comprehends how to use the system easily.</td>
<td><blockquote>
<p>3.40</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.43</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The user can learn to use the system easily.</td>
<td><blockquote>
<p>3.60</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.57</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td>The user can use the system without much effort.</td>
<td><blockquote>
<p>3.40</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.43</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The interface looks good.</td>
<td><blockquote>
<p>3.60</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.57</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td><strong>Weighted Mean of Usability</strong></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 7 presented the ratings for Usability. The items on learning the system and on the look of the interface obtained the highest overall weighted mean (3.57 each), while the items on understanding how to use the system and on using it without much effort obtained 3.43 each. The IT professionals and the owner and staff both gave a category weighted mean of 3.50. The category weighted mean was 3.50, interpreted as Strongly Agree. One respondent rated "Can the user use the system without much effort?" as Disagree.

## D. Efficiency 

**Table 8. Weighted Means for Efficiency**

<table>
<colgroup>
<col style="width: 16%" />
<col style="width: 20%" />
<col style="width: 21%" />
<col style="width: 19%" />
<col style="width: 22%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>Item</strong></th>
<th><blockquote>
<p><strong>Weighted Mean of IT</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Weighted Mean of Client</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Overall Weighted Mean</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>The system responds quickly.</td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.00</p>
</blockquote></td>
<td>3.36</td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The system utilizes resources efficiently.</td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>4.00</p>
</blockquote></td>
<td><blockquote>
<p>3.64</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td><strong>Weighted Mean of Efficiency</strong></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 8 presented the ratings for Efficiency. The item on the efficient use of resources obtained an overall weighted mean of 3.64, while the item on how quickly the system responds obtained 3.36. The owner and staff rated the response speed 3.00 (Agree), lower than the 3.50 given by the IT professionals, but rated the use of resources 4.00. The category weighted mean was 3.50, interpreted as Strongly Agree, and no respondent rated any item Disagree or Strongly Disagree.

# E. Portability 

**Table 9. Weighted Means for Portability**

<table>
<colgroup>
<col style="width: 16%" />
<col style="width: 21%" />
<col style="width: 21%" />
<col style="width: 18%" />
<col style="width: 21%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>Item</strong></th>
<th><strong>Weighted Mean of IT</strong></th>
<th><blockquote>
<p><strong>Weighted Mean of Client</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Overall Weighted Mean</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>The software can be installed easily.</td>
<td><blockquote>
<p>3.30</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.36</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The software can easily replace other software.</td>
<td><blockquote>
<p>3.30</p>
</blockquote></td>
<td><blockquote>
<p>3.00</p>
</blockquote></td>
<td><blockquote>
<p>3.21</p>
</blockquote></td>
<td><blockquote>
<p>Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td><strong>Weighted Mean of Portability</strong></td>
<td><blockquote>
<p>3.30</p>
</blockquote></td>
<td><blockquote>
<p>3.25</p>
</blockquote></td>
<td><blockquote>
<p>3.29</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 9 presented the ratings for Portability. The item on easy installation obtained an overall weighted mean of 3.36 (Strongly Agree), while the item on easily replacing other software obtained 3.21, interpreted as Agree. The owner and staff rated the replacement item 3.00, and the IT professionals rated it 3.30. The category weighted mean was 3.29, interpreted as Strongly Agree, which was the lowest among the six quality characteristics. Three respondents rated the replacement item as Disagree.

# F. Maintainability 

**Table 10. Weighted Means for Maintainability**

<table>
<colgroup>
<col style="width: 20%" />
<col style="width: 19%" />
<col style="width: 20%" />
<col style="width: 17%" />
<col style="width: 21%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>Item</strong></th>
<th><blockquote>
<p><strong>Weighted Mean of IT</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Weighted Mean of Client</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Overall Weighted Mean</strong></p>
</blockquote></th>
<th><blockquote>
<p><strong>Verbal Interpretation</strong></p>
</blockquote></th>
</tr>
</thead>
<tbody>
<tr class="odd">
<td>Faults can be easily diagnosed.</td>
<td><blockquote>
<p>3.40</p>
</blockquote></td>
<td><blockquote>
<p>3.75</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="even">
<td>The software can be tested easily.</td>
<td><blockquote>
<p>3.20</p>
</blockquote></td>
<td><blockquote>
<p>3.50</p>
</blockquote></td>
<td><blockquote>
<p>3.29</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
<tr class="odd">
<td><strong>Weighted Mean of Maintainability</strong></td>
<td><blockquote>
<p>3.30</p>
</blockquote></td>
<td><blockquote>
<p>3.63</p>
</blockquote></td>
<td><blockquote>
<p>3.39</p>
</blockquote></td>
<td><blockquote>
<p>Strongly Agree</p>
</blockquote></td>
</tr>
</tbody>
</table>

Table 10 presented the ratings for Maintainability. The item on diagnosing faults obtained an overall weighted mean of 3.50, while the item on testing the software easily obtained 3.29. The owner and staff gave a category weighted mean of 3.63, which was higher than the 3.30 given by the IT professionals. The category weighted mean was 3.39, interpreted as Strongly Agree. One respondent rated "Can the software be tested easily?" as Disagree.

**G. Summary of Results**

**Table 11. Summary of Weighted Means per Quality Characteristic**

| **Quality Characteristic** | **Weighted Mean of IT** | **Weighted Mean of Client** | **Overall** | **Verbal Interpretation** |
|----------------------------|-------------------------|-----------------------------|-------------|---------------------------|
| Functionality              | 3.63                    | 3.92                        | 3.71        | Strongly Agree            |
| Reliability                | 3.50                    | 3.63                        | 3.54        | Strongly Agree            |
| Usability                  | 3.50                    | 3.50                        | 3.50        | Strongly Agree            |
| Efficiency                 | 3.50                    | 3.50                        | 3.50        | Strongly Agree            |
| Portability                | 3.30                    | 3.25                        | 3.29        | Strongly Agree            |
| Maintainability            | 3.30                    | 3.63                        | 3.39        | Strongly Agree            |
| **Overall Weighted Mean**  | 3.47                    | 3.58                        | 3.50        | Strongly Agree            |

Table 11 summarized the category weighted means of the six quality characteristics. Functionality obtained the highest category weighted mean (3.71) and Portability the lowest (3.29), and all six characteristics were interpreted as Strongly Agree. The owner and staff gave an overall weighted mean of 3.58, slightly higher than the 3.47 given by the IT professionals. The overall weighted mean of the system was 3.50, interpreted as Strongly Agree. Only 5 of the 210 individual ratings (about 2%) were Disagree, and none were Strongly Disagree.

**CHAPTER 4**

**Conclusion and Recommendations**

This chapter presents the conclusions drawn from the results of the testing and evaluation of IntelliWare: A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support for Shea and Nicole’s General Merchandise Store and the recommendations for the further enhancement of the system.

**Conclusion**

Based on the findings of the study, the following conclusions are drawn:

1.  1.IntelliWare was developed as a web-based inventory management system for Shea and Nicole's General Merchandise Store. It replaced the store's manual recording with a central database of product and transaction records, with products registered either manually or through the camera, so that inventory records stayed accurate and up to date. The evaluators found the system functional, with a Functionality composite mean of 3.71.

2.  Stock levels were monitored by classifying each product as Normal, Low Stock, Out of Stock, or Overstock according to its current stock, reorder point, and Days of Supply. The dashboards and notifications displayed these conditions, which allowed the store to act on shortages and excess stock before they became problems. The evaluators agreed that the software could perform the tasks required (3.57).

3.  Data analytics and rule-based classification worked together in the system. The Daily Sales Velocity and Days of Supply were computed from the transaction records, and If-Then rules used these values to classify products by movement (Fast-Moving, Medium-Moving, Slow-Moving, and Non-Moving) and by stock condition, while the forecasting module identified seasonal demand. The evaluators agreed that the results were as expected (3.86), the highest statement mean in the evaluation.

4.  IntelliWare supported decision-making by generating inventory status, fast-moving and slow-moving, stock replenishment, and expiration reports, by issuing automated low-stock, overstock, and near-expiration alerts, and by presenting inventory insights through role-based dashboards. These gave the owner and staff the information they needed to decide on restocking.

5.  The If-Then rules evaluated every product automatically whenever a user accessed the system or completed a transaction. A product that fell below its reorder point was classified as Low Stock and flagged with a notification showing the remaining quantity and the reorder point. The system was stable in running these processes, as shown by the Reliability composite mean of 3.54.

6.  Seasonal demand was identified and presented through the Machine Learning Forecasting Module and the Philippine Market and Seasonal Demand Intelligence view, which showed the seasonal period, the events expected to drive consumption, the projected demand lift per category, and the products at risk of stockout. The system also provided a real-time Gemini AI Daily Consumer Pulse that estimated daily and weekly consumer demand, including payday cycles and weather patterns, against live inventory, in addition to the static seasonal profile. These allowed the store to prepare its inventory before peak periods. Since the System Evaluation Tool had no statement specific to seasonal demand, this conclusion is supported by the Functionality composite mean of 3.71).

7.  The developed system was acceptable based on the ISO/IEC 25010:2023 Software Quality Model. The respondents found it functional (3.71), stable (3.54), easy to use (3.50), efficient (3.50), portable (3.29), and maintainable (3.39), with a composite mean of 3.50. The lowest-rated statements were "The software can easily replace other software" (3.21), "The software can be tested easily" (3.29), and "The system responds quickly" (3.36).

Overall, the composite mean of 3.50 shows that IntelliWare was functional, stable, and acceptable to its users, and that it is a workable replacement for the store's manual inventory recording.

**Recommendations**

**Based on the foregoing conclusions, the following further enhancements are recommended:**

1.  IntelliWare may be extended with a point-of-sale module so that stock levels update automatically with every sale, and with supplier ordering so that a reorder alert can lead directly to a purchase order.

2.  The forecasting and seasonal demand features may be enhanced by tracking the forecast accuracy the system reports as more sales history accumulates, and by including an evaluation item for seasonal demand in future studies.

3.  A bulk import tool and a guided setup may be added so that other stores can bring in their existing spreadsheet or notebook records and adopt the system quickly.

4.  Automated testing and detailed technical documentation may be added to support smooth maintenance and future updates, and database queries may be optimized so the system stays fast as transactions grow.

5.  Future researchers may adapt IntelliWare to other retail and business sectors, building on its data analytics, rule-based classification, and camera-based registration.

# 

# References 

Dr. Uma, S., & Kaviya, P. (2026). Smart inventory management system: A webbased solution for automated retail inventory operations. In International Journal of Current Science (IJCSPUB) (Vol. 16, Issue 1, pp. 444–445) \[Journalarticle\]. [<u>https://rjpn.org/ijcspub/papers/IJCSP26A1055.pdf</u>](https://rjpn.org/ijcspub/papers/IJCSP26A1055.pdf)

Eweoya, I., Adigun, T., Akinduro, F., Oladipo, S., Awoniyi, A., Okorie, G., Sodiq, K., Lawal, O., & Fabiyi, O. (2025). Design and implementation of a web-based inventory management system. Current Trends in Information Communication

Technology Research, 4(1). [<u>https://doi.org/10.61867/pcub.v4i1a.200</u>](https://doi.org/10.61867/pcub.v4i1a.200) Gaborno, J. A. B., Ernal, J. G. G., Gange, M. S. R. L., Mucho, J. G. P., Cariaga, K. G., Ibieza, D. B., Egael, L. J. J., & Gentapanan, R. G. (2025). Microbusiness owners' inventory management practices. International Journal of Research and Innovation in Social Science, 9(3).

[<u>https://dx.doi.org/10.47772/IJRISS.2025.909000373</u>](https://dx.doi.org/10.47772/IJRISS.2025.909000373)

Gong, H., & Hui, H. (2024). Research on intelligent inventory management system based on automation and real-time data processing. Mathematical Modeling and Algorithm Application, 3(2), 39–43 [<u>https://doi.org/10.54097/4yafsk85</u>](https://doi.org/10.54097/4yafsk85)

Kumar, P., Choubey, D., Amosu, O. R., & Ogunsuji, Y. M. (2024). AI-enhanced inventory and demand forecasting: Using AI to optimize inventory management and predict customer demand. World Journal of Advanced Research and Reviews, 23(1), 1931–1944.

[<u>https://doi.org/10.30574/wjarr.2024.23.1.2173</u>](https://doi.org/10.30574/wjarr.2024.23.1.2173)

Mamar, R. R. M. C., Dairo, C. F. T., Maravilla, E. C., Pisuena, J. S., & Soberano, K. T. (2026). A Data-Driven POS system for sales monitoring and inventory management. Zenodo (CERN European Organization for Nuclear Research). [<u>https://doi.org/10.5281/zenodo.20412208</u>](https://doi.org/10.5281/zenodo.20412208)

Puican Rodríguez, V. H., Toro López, R. D. J., & García Vera, W. R. (2025).

Innovations in inventory management to improve the profitability of local SMEs. F1000Research, 14, 853.

[<u>https://doi.org/10.12688/f1000research.168900.3</u>](https://doi.org/10.12688/f1000research.168900.3)

Sumacot, C., Matubay, A., Ruiz, A., Lariosa, M., & Dadap, R. (2026). Design and development of a web-based inventory management system for the Business Affairs and Resource Generating Office of SLSU-Hinunangan Campus \[Capstone project\]. Southern Leyte State University. Studocu.

[<u>https://www.studocu.com/ph/document/polytechnic-university-of-</u>](https://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644)

[<u>the</u>https://www.studocu.com/ph/document/polytechnic-university-of-thehttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644philippines/bachelor-of-science-in-information-technology/design-and-developmenthttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644of-an-inventory-management-system-for-bargo-ithttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644capstone/161073857?sid=695731041784283644<u>philippines/bachelor-of-science-in</u>https://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644<u>information-technology/design</u>https://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644<u>and</u>https://www.studocu.com/ph/document/polytechnic-university-of-thehttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644philippines/bachelor-of-science-in-information-technology/design-and-developmenthttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644of-an-inventory-management-system-for-bargo-ithttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644capstone/161073857?sid=695731041784283644<u>development-of-an-inventory</u>https://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644<u>management-system-for-bargo-it</u>https://www.studocu.com/ph/document/polytechnichttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644university-of-the-philippines/bachelor-of-science-in-information-technology/designhttps://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644and-development-of-an-inventory-management-system-for-bargo-it-](https://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644)

[capstone/161073857?sid=695731041784283644<u>capstone/161073857?sid=695731</u> <u>041784283644</u>](https://www.studocu.com/ph/document/polytechnic-university-of-the-philippines/bachelor-of-science-in-information-technology/design-and-development-of-an-inventory-management-system-for-bargo-it-capstone/161073857?sid=695731041784283644)

Šušter, I., Karabašević, D., Stanujkić, D., Popović, G., Veljić, A., & Marković, M. (2025). Data protection and security in MySQL database. Quaestus, 27(1), 125–

137\.

[<u>https://www.ceeol.com/search/article-detail?id=1393381</u>](https://www.ceeol.com/search/article-detail?id=1393381)

Timotius, E. (2025). Mobile application development for inventory management using barcode technology. International Journal of Emerging Financial and Economic Research, 4. [<u>https://wikep.net/index.php/IJEFE/article/view/77/73</u>](https://wikep.net/index.php/IJEFE/article/view/77/73)

Tanaman, M., Lloyd, J., Baylosis, A., Abiles, J., Lester, M., Catungal, P., & Encarnacion, P. (2023). Web-based inventory management system. International Journal of Science and Applied Information Technology, 12(5), 44–48.

[<u>https://doi.org/10.30534/ijsait/2023/021252023</u>](https://doi.org/10.30534/ijsait/2023/021252023)

Wei, C. C., Ramiah, S. A., & Razali, N. F. (2023). Inventory Management Systems (IMS). The Journal of Applied Technology and Innovation, 7(3). [<u>https://doi.org/10.65136/jati.v7i1.129</u>](https://doi.org/10.65136/jati.v7i1.129)
