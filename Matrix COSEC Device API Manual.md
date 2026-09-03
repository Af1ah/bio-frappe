### **COSEC Devices API User Guide** 



S EC UR I T Y  S OL UT I O NS 



#### S EC UR I TY  SO L UT I ON S 

**COSEC Devices API User Guide** 

## **Documentation Disclaimer** 

Matrix Comsec reserves the right to make changes in the design or components of the product as engineering and manufacturing may warrant. Specifications are subject to change without notice. 

This is a general documentation for all variants of the product. The product may not support all the features and facilities described in the documentation. 

Information in this documentation may change from time to time. Matrix Comsec reserves the right to revise information in this publication for any reason without prior notice. Matrix Comsec makes no warranties with respect to this documentation and disclaims any implied warranties. While every precaution has been taken in the preparation of this system manual, Matrix Comsec assumes no responsibility for errors or omissions. Neither is any liability assumed for damages resulting from the use of the information contained herein. 

Neither Matrix Comsec nor its affiliates shall be liable to the buyer of this product or third parties for damages, losses, costs or expenses incurred by the buyer or third parties as a result of: accident, misuse or abuse of this product or unauthorized modifications, repairs or alterations to this product or failure to strictly comply with Matrix Comsec operating and maintenance instructions. 

### **Copyright** 

All rights reserved. No part of this system manual may be copied or reproduced in any form or by any means without the prior written consent of Matrix Comsec. 

_Version 1.0 Release date: May 31, 2014_ 

## **Contents** 

|**Contents ..........................................................................................................................................................i**|
|---|
|**List of Tables ..................................................................................................................................................3**|
|**About the Document .....................................................................................................................................1**|
|_Document Conventions ....................................................................................................................................... 1_|
|_Document Organization ...................................................................................................................................... 2_|
|_Who Can Use This Document ............................................................................................................................. 2_|
|**API Overview ..................................................................................................................................................3**|
|_How It Works ....................................................................................................................................................... 3_|
|_Supported Devices .............................................................................................................................................. 4_<br>|
|_General Features ................................................................................................................................................ 4_|
|_What the User Should Know ............................................................................................................................... 4_|
|_Prerequisite ......................................................................................................................................................... 5_|
|_Authentication ..................................................................................................................................................... 5_|
|_HTTP Request-Response ................................................................................................................................... 5_|
|_Communication Flow ..................................................................................................................................................................... 6_|
|_Request Format ............................................................................................................................................................................. 6_|
|_Response Format .......................................................................................................................................................................... 7_|
|_URL Syntax .................................................................................................................................................................................... 8_|
|_Common Actions ........................................................................................................................................................................... 9_|
|_Additional Information ....................................................................................................................................... 10_|
|**Supported APIs ............................................................................................................................................11**|
|_API Quick Reference ......................................................................................................................................... 11_|
|**Device Configuration ...................................................................................................................................13**|
|_Basic Device Configuration ............................................................................................................................... 14_|
|_Function Key Configuration ............................................................................................................................... 17_|
|_Reader Configuration ........................................................................................................................................ 18_|
|_Finger Reader Parameter Configuration ........................................................................................................... 20_|
|_Palm Sensor Parameter Configuration ............................................................................................................. 21_|
|<br>_Enrollment Configuration ................................................................................................................................... 22_|
|<br>_Access Settings Configuration .......................................................................................................................... 24_|
|<br>_Alarm Configuration .......................................................................................................................................... 25_|
|_Date and Time Configuration ............................................................................................................................ 26_|
|_Door Features Configuration ............................................................................................................................. 29_|
|_System Timers Configuration ............................................................................................................................ 31_|
|_Special Function Configuration ......................................................................................................................... 32_|
|**User Configuration ......................................................................................................................................33**|
|_Setting/Retrieving User Configuration ............................................................................................................... 34_|
|_Setting a User Photo ......................................................................................................................................... 37_|
|<br>_Deleting a User ................................................................................................................................................. 39_|
|_Setting User Credentials ................................................................................................................................... 40_|
|_Retrieving User Credentials .............................................................................................................................. 41_|
|_Deleting User Credentials ................................................................................................................................. 43_|
|**Enrollment ....................................................................................................................................................44**|
|_Enrolling a User ................................................................................................................................................. 45_|
|_Enrolling Special Cards ..................................................................................................................................... 48_|
|**Events ...........................................................................................................................................................49**|
|_Retrieving Events .............................................................................................................................................. 50_|



**_Table of Contents_** 

**_i_** 

|_Retrieving Events in the TCP Socket ................................................................................................................ 52_|
|---|
|**Sending Commands to Device ...................................................................................................................54**|
|**Error Responses ..........................................................................................................................................57**|
|**API Response Codes ...................................................................................................................................59**|
|**Appendix ......................................................................................................................................................61**|



**_Table of Contents_** 

**_ii_** 

## **List of Tables** 

|Table: API Quick Reference ..........................................................................................................................11|
|---|
|Table: Device Configuration Parameters .......................................................................................................14|
|Table: Function Key Configuration Parameters .............................................................................................17|
|Table: Reader Configuration Parameters ......................................................................................................18|
|Table: Finger Reader Parameter Configuration - Parameters .......................................................................20|
|Table: Finger Reader Parameter Configuration - Parameters .......................................................................21<br>|
|Table: Enrollment Configuration Parameters .................................................................................................22|
|Table: Access Settings Configuration Parameters ........................................................................................24<br>|
|Table: Alarm Configuration Parameters ........................................................................................................25|
|Table: Date and Time Configuration Parameters ..........................................................................................26<br>|
|Table: Door Features Configuration Parameters ...........................................................................................29|
|Table: System Timers Configuration Parameters ..........................................................................................31<br>|
|Table: Special Function Configuration Parameters .......................................................................................32|
|Table: User Configuration Parameters ..........................................................................................................34|
|Table: Setting a User Photo - Parameters .....................................................................................................37|
|Table: Delete User - Parameters ...................................................................................................................39|
|Table: Setting User Credentials - Parameters ...............................................................................................40|
|Table: Retrieving User Credentials - Parameters ..........................................................................................41|
|Table: Deleting User Credentials - Parameters .............................................................................................43|
|Table: Enrolling User - Parameters ...............................................................................................................45|
|Table: Enroll Special Cards - Parameters .....................................................................................................48<br>|
|Table: Retrieving Events - Parameters ..........................................................................................................50|
|Table: Value Range for Event Sequence Numbers .......................................................................................50|
|Table: Retrieving Events in the TCP Socket - Parameters ............................................................................52|
|Table: List of Commands to Device ...............................................................................................................54|
|Table: Get Credential Count Command - Parameters ...................................................................................54|
|Table: Deleting Credentials for All Users - Parameters .................................................................................56|
|Table: API Response Codes .........................................................................................................................59|
|Table: Universal Time Zone Reference .........................................................................................................61|
|Table: List of Events ......................................................................................................................................62|
|Table: Size of Event Fields ............................................................................................................................64|
|Table: User Events ........................................................................................................................................64|
|Table: Special Function Codes Reference ....................................................................................................66|
|Table: Field 3 Detail (User Events) Reference ..............................................................................................67|
|Table: Information of Bit 0 and Bit 1 ..............................................................................................................67|
|Table: Information of Bit 4 and Bit 8 ..............................................................................................................67|
|Table: Door Events........................................................................................................................................ 68|
|Table: Alarm Events ......................................................................................................................................69|
|Table: System Events ....................................................................................................................................70|



# **About the Document** 

Welcome to the _COSEC Devices API User Guide_ . This document will provide you a comprehensive overview and complete user-guidance for all _COSEC Devices APIs_ . You can learn more about COSEC APIs, browse through detailed descriptions of individual APIs and test them using sample scenarios. 

### **Document Conventions** 

This API User Guide will follow a set of document conventions to make it consistent and easier for you to read. These are as follows: 

**1.** Text within angle brackets (e.g. “<request-type>”) denotes content in URL syntax and should be replaced with either a value or a string. The angle brackets should be ommitted in all instances except those used to denote “tags” within XML responses (e.g. “<name></name>”). 

**2.** Cross-references and other links appear as follows: _Document Conventions_ 

For e.g. To learn more about APIs, please refer to section _Who Can Use This Document_ 

**3.** The term _device_ used in this document, will refer only to direct doors. 

**4.** Any expression resembling **_<x~y>_** , indicates that the field should be repeated for index values **_x_** to index values **_y_** . This is to avoid duplicating the same parameter for multiple index numbers. 

**5.** Additional information about any section appears in the form of notices. The following symbols have been used for notices to draw your attention to important items. 

**_Important:_** _to indicate something that requires your special attention or to remind you of something you might need to do when you are using the system._ 



**_Caution:_** _to indicate an action or condition that is likely to result in malfunction or damage to the system or your property._ 

**_Warning:_** _to indicate a hazard or an action that will cause damage to the system and or cause bodily harm to the user._ 

**_Tip:_** _to indicate a helpful hint giving you an alternative way to operate the system or carry out a procedure, or use a feature more efficiently._ 

Matrix COSEC Devices API User Guide 

1 

### **Document Organization** 

This document has been organized into the following topics: 

**1.** About the Document 

**2.** API Overview 

**3.** Supported APIs 

**4.** Device Configuration 

**5.** User Configuration 

**6.** Enrollment 

**7.** Events 

**8.** Sending Commands to Devices 

**9.** Error Responses 

**10.** API Response Codes 

**11.** Appendix 

Topics 1 and 2 will provide a general understanding of COSEC Devices APIs and the basic interface communication. Topic 3 provides a list of all supported APIs with a quick reference list for the user. Topics 4-8 provide an overview of API categories with detailed explanation of individual APIs. The following information has been provided on each request type: 

- Description of the functionality. 

- Action requested. 

- Generic query syntax. 

- Mandatory and optional parameters (argument-value table). 

- Examples ( _Sample Request_ and _Sample Response_ ). 

Topic 9 provides illustrations of error messages. Topic 10 provides a list of API Response Codes and their meaning. The _Appendix_ will provide additional material for the user’s reference. 



_For a list of all tables provided in the document, refer to List of Tables. Click on the links to view the respective tables for the required data._ 

### **Who Can Use This Document** 

The COSEC Devices API User Guide is meant for _third-party software developers_ who wish to operate COSEC Devices via another remote application. This guide will provide information to users on how to request and receive services from COSEC Devices using a COSEC API. 

Matrix COSEC Devices API User Guide 

2 



<!-- Start of picture text -->
SS) A User 4 QO<br>= ee 8S= LANIWANInternet User! N ‘a<br>COSEC Database COSEC Web COSEC<br>Server Server Web Clients<br>LANAVAN<br>Intemet<br>Direct DOOR<br><!-- End of picture text -->



<!-- Start of picture text -->
| LI AN/nte rnetWAN |; &<br><!-- End of picture text -->

### **Supported Devices** 

COSEC Devices APIs are dependant on the device type. Currently, Device APIs are supported on the following COSEC Door Controllers and their variants: 

- COSEC Direct Door V2 

- COSEC Path Controller 

- COSEC Wireless Door 

- COSEC NGT Door 

- COSEC PVR Door 

- COSEC Vega Controller 

### **General Features** 

All COSEC APIs - 

- Are Web-based _HTTP_ APIs. 

- Use basic _HTTP Request-Response_ for interface communication. 

- Generate response in either _text_ or _XML_ (Extensible Markup Language) format. 

- Use simple _HTTP commands_ such as _GET_ , _SET_ , _DELETE_ etc. 

- Use a generic syntax for all queries. 

- Support some predefined parameters and their corresponding values for each action. Each parameter will either be mandatory or bear a system-defined default value (when no value is specified). 

- Use a mandatory parameter **_action_** universally, which takes action values (such as **_get, set, delete_** etc.) and specifies the action to be requested. 

### **What the User Should Know** 

It is assumed that developers using this document have prior knowledge of: 

- Basic functioning of the COSEC system 

- Basic HTTP request-response communication 

- XML 

Matrix COSEC Devices API User Guide 

4 

### **Prerequisite** 

In order to use a COSEC API, the user will require: 

- A COSEC Device (pre-installed) 

- A network enabled for accessing the COSEC Device. 

- The credentials for API Authentication 



_For information on installing a COSEC device and assigning an IP address to it, please refer to the respective device documentation._ 

### **Authentication** 

The device shall request basic authentication for granting access. Default username and password for HTTP session authentication are: 

Username: admin Password: 1234 

### **HTTP Request-Response** 

Basic HTTP communication is based on a request-response paradigm. The message structure for both request and response has a generic format. 

HTTP-message = Request | Response ; HTTP/1.1 messages 

|Generic-message = start-line|_The start line_|
|---|---|
|*(message-header CRLF)|_Zero or more header fields or ‘headers’_|
|CRLF|_An empty line_|
|[Message-body]|_A message-body (chunk or payload)_|



Start-line = Request-Line | Status-Line 

Matrix COSEC Devices API User Guide 

5 

##### **Communication Flow** 

The communication takes place in the following manner: 

**1.** The client checks availablility of the device. 

**2.** If available, the client issues a request for the device. 



<!-- Start of picture text -->
SERVER CLIENT<br>C osec  3  rd party<br>D evice softw are<br>R EQU EST: w ith syntax,  but no authentication / invalid authentication<br>R ESPON SE: denied<br>R esponse status code ( 4xx) along w ith  suggestions of supported<br>authentication method<br>R EQU EST: invalid syntax but valid authentication<br>R ESPON SE: denied<br>R esponse status code (4xx)<br>R EQU EST: w ith syntax and supported passw ord authentication<br>R ESPON SE: successful<br>R equested Message body along w ith response status code i .e 2xx<br>R EQU EST: the server is not<br>connected<br>Fig: comm unication flow<br><!-- End of picture text -->

**3.** The device parses the request for the action to be taken. 

**4.** In case of an error ( _invalid syntax_ , _invalid authentication_ etc.), the request is denied and an error response is returned. Else, the requested data is returned with the appropriate response code. 

##### **Request Format** 

All HTTP Requests follow a generic message format. It consists of the following components: 



<!-- Start of picture text -->
This line is constituted by the following three elements which must be<br>separated by a space:<br>• The method type (GET, HEAD, POST, PUT etc.)<br>1.  Request Line • The requested URL<br>• The HTTP version to use<br>For e.g.:<br>GET http://192.168.1.2/device.cgi/command?action=geteventcount HTTP/1.0<br><!-- End of picture text -->

Matrix COSEC Devices API User Guide 

6 

|2.|Header Fields|Add information about the request using these header fields:<br>•<br>A General Header (<Header-name>:<value>).<br>•<br>A Request Header (<Header-name>:<value>).|
|---|---|---|
|||•<br>An Entity Header (<Header-name>:<value>).|
|3|Empty Line|This is an empty line separating headers from the message body.|
|4|Message Body|This is the chunk or payload.|



###### **Example:** 

GET http://matrix.com/ HTTP/1.0 Accept: text/html If-Modified-Since: Saturday, 15-January-2000 14:37:11 GMT User-Agent: Mozilla/4.0 (compatible; MSIE 5.0; Windows 95) 

##### **Response Format** 

An HTTP response is a collection of lines sent by the server to the client. A generic HTTP response format will resemble the following: 

VERSION-HTTP CODE EXPLANATION<crlf> HEADER: Value<crlf> . . . HEADER: Value<crlf> Empty line<crlf> BODY OF THE RESPONSE 

It consists of the following components: 

- This line is constituted by the following three elements which must be separated by a space: 

- 1. A status line • The version of the protocol used (e.g. _HTTP/1.0_ ). • The status code (indicates the status of the request being processed). 

- • The explanation of the code. 

- These optional lines allow additional information to be added to the response header. This information appears in the form of a name 

- 2. The response header fields indicating the header type followed by a value for the header type. The name and value are separated by a colon (:). 

- 3. The body of the response Contains the requested data. 

Matrix COSEC Devices API User Guide 

7 

##### **Example** 

When the server gets a request, it will respond with a standard HTTP status code as illustrated in the following sample response: 

HTTP/1.0 200 OK Date: Sat, 15 Jan 2000 14:37:12 GMT Server: Microsoft-IIS/2.0 Content-Type: text/HTML Content-Length: 1245 Last-Modified: Fri, 14 Jan 2000 08:25:13 GMT 

**_HTTP Status Codes:_** _Status codes are 3-digit numeric codes returned in HTTP responses that enable recipients to understand the successful or failed status of the request issued. In general, codes in the 1xx range indicate an informational message only, 2xx codes indicate a successful request, 3xx codes indicate an incomplete request that requires further action, 4xx codes point at client-side errors while 5xx codes point at server-side errors._ 



##### **URL Syntax** 

All COSEC APIs follow a common HTTP query syntax for the third party to generate a request. The generic URL is stated below. 

###### **Syntax** 

http://<deviceIP:deviceport>/device.cgi/<request-type>?<argument>=<value>[&<argument>=<value>......] 

Take a close look at the URL and its basic elements: 

|**URL element**|**Description**|
|---|---|
|_http://_|This is the protocol used to communicate with the client.<br>**Note:**All HTTP commands are in plain text, and almost all HTTP requests<br>are sent using TCP port 80, though any port can be used.|
|_<deviceIP:deviceport>_|This identifies the device with which communication is to be performed. It<br>consists of two components:<br>deviceIP: Device IP address<br>deviceport: Device Port Number|
|_device.cgi_|This is a mandatory entity required to specify the CGI directory for all the<br>device-related commands.|
|_<request-type>_|This specifies the type of API request. For the mandatory request types,<br>please refer to the individual API descriptions.|



Matrix COSEC Devices API User Guide 

8 

|**URL element**|**Description**|
|---|---|
|_<argument>_|This defines a specific action or command depending on the function to be<br>performed.<br>A mandatory argument for all COSEC API functions is_action_. This argument<br>always takes an action as its value (For eg._action=get_).|
||For more information on the common HTTP actions used in COSEC APIs,<br>please refer to section_Common Actions._|
|_<value>_|These are argument values that determine the output.|



###### **Example** 

Let us assume that the target device has the IP address 192.168.x.y and the device port number is _80_ . The user wants to fetch basic configured parameters for the device. In this case, a sample request would resemble the following: 

http://192.168.x.y:80/device.cgi/device-basic-config?action=get&format=xml 

In this case, the query uses an **_action=get_** parameter which is commonly used to retrieve information from the device-side. The URL takes another argument called **_format_** which specifies that the response returned should be in the XML format. 



- Special characters ( &, **‘** , **“** , **<** , **>** , #, % and **;** ) will not be allowed in arguments or their values. Special character “ **&** ” will be allowed as a separator between consecutive arguments and “ **?** ” will be allowed as a separator between the request-type and an argument. 

- The request line and headers must all end with <CR><LF> that is carriage return character followed by a line feed character. 

- The status line and header must all end with <CR><LF>. 

- The empty line must consist of only <CR><LF> and no other white space. 

##### **Common Actions** 

The following actions are commonly used in COSEC APIs as values for the ‘ **_action_** ’ argument: 

|**Action**|**Use**|
|---|---|
|_GET_|To fetch required data from device.|
|_SET_|To set required parameters for a given function.|
|_GETDEFAULT_|This is used to get default the parameters of all/ specified<br>argument. If any argument is specified then default value of<br>that particular argument is returned else default value of<br>complete group is returned.|
|_SETDEFAULT_|This is used to default the parameters. If any argument is<br>specified then default that particular value else default<br>complete group|



Matrix COSEC Devices API User Guide 

9 





‘e1@)* +y | a @) http://192.168.103.119/device.cgi/device-basie-config?action=get&format=xral : File Edit View Favorites Tools Help ie Favorites | @ http://192.168.103.119/device.cgi/device-basic-c... 

- <?xml version="1.0" encoding="utf-8" ?> 

- ~ <COSEC_API> <app>1</app> 

- <name > <asc-code>0</asc-code> <max- fingers >1 </max- fingers> 

- </COSEC_API> 

## **Supported APIs** 

COSEC Devices support the following groups of APIs categorized on the basis of functions to be performed: 

- Device Configuration 

- User Configuration 

- Enrollment 

- Events 

- Sending Commands to Device 

### **API Quick Reference** 

This section enables users to view a quick reference list of all supported Devices APIs discussed in the guide. The following table lists all functions along with their respective HTTP Request URLs and the applicable action values. For further details on supported parameters and values, refer to the respective argument-value tables for individual APIs (See _List of Tables)_ . 

###### **Table: API Quick Reference** 

|**URL**|**Actions**|**Functionality**|
|---|---|---|
|http://<deviceIP:deviceport>/device.cgi/device-basic-<br>config?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Basic Device Configuration|
|http://<deviceIP:deviceport>/device.cgi/function-<br>key?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Function Key Configuration|
|http://<deviceIP:deviceport>/device.cgi/reader-<br>config?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Reader Configuration|
|http://<deviceIP:deviceport>/device.cgi/enroll-<br>options?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Enrollment Configuration|
|http://<deviceIP:deviceport>/device.cgi/access-<br>setting?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Access Settings Configuration|
|http://<deviceIP:deviceport>/device.cgi/<br>alarm?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Alarm Configuration|
|http://<deviceIP:deviceport>/device.cgi/date-<br>time?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Date and Time Configuration|
|http://<deviceIP:deviceport>/device.cgi/door-<br>feature?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Door Features Configuration|
|http://<deviceIP:deviceport>/device.cgi/system-<br>timer?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|System Timers Configuration|
|http://<deviceIP:deviceport>/device.cgi/special-<br>function?action=<value>[&<argument>=<value>….]|get, set, getdefault,<br>setdefault|Special Function Configuration|
|http://<deviceIP:deviceport>/device.cgi/<br>users?action=<value>[&<argument>=<value>….]|set, get|Setting/Retrieving User Configuration|
|http://<deviceIP:deviceport>/device.cgi/<br>userphoto?action=<value>[&<argument>=<value>….]|get, set, delete|Setting a User Photo|
|http://<deviceIP:deviceport>/device.cgi/<br>users?action=delete[&<argument>=<value>….]|delete|Deleting a User|
|http://<deviceIP:deviceport>/device.cgi/<br>credential?action=set[&<argument>=<value>….]|set|Setting User Credentials|
|http://<deviceIP:deviceport>/device.cgi/<br>credential?action=get[&<argument>=<value>….]|get|Retrieving User Credentials|



Matrix COSEC Devices API User Guide 

11 

###### **Table: API Quick Reference** 

|**URL**|**Actions**|**Functionality**|
|---|---|---|
|http://<deviceIP:deviceport>/device.cgi/<br>credential?action=delete[&<argument>=<value>….]|delete|Deleting User Credentials|
|http://<deviceIP:deviceport>/device.cgi/<br>enrolluser?action=enroll[&<argument>=<value>….]|enroll|Enrolling a User|
|http://<deviceIP:deviceport>/device.cgi/<br>enrollspcard?action=enroll[&<argument>=<value>….]|enroll|Enrolling Special Crads|
|http://<deviceIP:deviceport>/device.cgi/<br>events?action=getevent[&<argument>=<value>….]|getevent|Retrieving Events|
|http://<deviceIP:deviceport>/device.cgi/tcp-<br>events?action=getevent[&<argument>=<value>….]|getevent|Retrieving Events in TCP Socket|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=clearalarm|clearalarm|Sending Commands -<br>Clear Alarm|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=getcount|getcount|Sending Commands -<br>Get Credential Count for Enrolled<br>Credentials|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=acknoledgealarm|acknoledgealarm|Sending Commands -<br>Acknowledge Alarm|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=lockdoor|lockdoor|Sending Commands -<br>Lock Door|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=unlockdoor|unlockdoor|Sending Commands -<br>Unlock Door|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=normalizedoor|normalizedoor|Sending Commands -<br>Normalize Door|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=getusercount|getusercount|Sending Commands -<br>Getting User Count on Device|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=geteventcount|geteventcount|Sending Commands -<br>Get Current Event Sequence<br>Number|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=systemdefault|systemdefault|Sending Commands -  Default the<br>System Configuration|
|http://<deviceIP:deviceport>/device.cgi/<br>command?action=deletecredential|deletecredential|Sending Commands -  Delete<br>Credentials for All Users|



Matrix COSEC Devices API User Guide 

12 

## **Device Configuration** 

This group of APIs enables users to perform the following types of device configuration: 

- Basic Device Configuration 

- Function Key Configuration 

- Reader Configuration 

- Finger Reader Parameter Configuration 

- Palm Sensor Parameter Configuration 

- Enrollment Configuration 

- Access Settings Configuration 

- Alarm Configuration 

- Date and Time Configuration 

- Door Features Configuration 

- System Timers Configuration 

- Special Function Configuration 

Matrix COSEC Devices API User Guide 

13 

### **Basic Device Configuration** 

**Description:** To set or retrieve basic configuration parameters for a device such as application type, name, Additional Security Code and maximum number of finger templates on device. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/device-basic-config?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Device Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|app|1, 2|No|To define the application.<br>1 = Advanced Access Control<br>2 = Basic Access Control|
|name|Alphanumeric,<br>Max. 30 characters|No|To identify/configure the device name.|
|asc-code|Numeric, 16 bits,<br>1-65535 range|No|To configure an Additional Security Code<br>(ASC). Should be non-zero.|
|Max-fingers|Single Template/Finger: 0-<br>9<br>where,<br>0 - 1 Finger<br>1 - 2 Fingers<br>2 - 3 Fingers<br>3 - 4 Fingers<br>4 - 5 Fingers<br>5 - 6 Fingers<br>6 - 7 Fingers<br>7 - 8 Fingers<br>8 - 9 Fingers<br>9 - 10 Fingers<br>Dual Template/Finger: 0-4<br>where,<br>0 - 1 Finger<br>1 - 2 Fingers<br>2 - 3 Fingers<br>3 - 4 Fingers<br>4 - 5 Fingers|No|Maximum no. of templates that can be stored<br>per user on this device.|
|format|text, xml|No|specifies the format in which the response is<br>expected.|



Matrix COSEC Devices API User Guide 

14 



_The_ **_Additional Security Code_** _is a code that can be written on a smart card for adding an additional layer of security check during door access._ 

_To get the default values for any parameter, use the_ **_action=getdefault_** _method. To restore configuration parameters on device to default values, use the_ **_action=setdefault_** _method._ 

##### Example 

Following are some test cases for your reference: 

###### **1. To get all parameters.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/device-basic-config?action=get 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <code> Content-Length: <type> Body: app=1 name= asc-code=0 max-fingers=1 

###### **2. To get device name, when expected value is blank and the response format is in text.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/device-basic-config?action=get&name&app 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <code> Content-Length: <type> Body: app=1 name= 

###### **3. To get device name, when the expected value is blank and the response format is XML.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/device-basic-config?action=get&name&app&format=xml 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <code> Content-Length: <type> Body: <COSEC_API> 

- <name></name> 

<app>1</app> 

- </COSEC_API> 

Matrix COSEC Devices API User Guide 

15 

###### **4. To set device name as blank– Valid argument.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/device-basic-config?action=set&name= 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <code> Content-Length: <type> Body: Response-Code=0 

Matrix COSEC Devices API User Guide 

16 

### **Function Key Configuration** 

**Description:** To set or retrieve configuration of Function Keys on the Device keypad. COSEC enables its users to map up to 4 special functions to the arrow keys on a Direct Door keypad. These functions can then be performed at the door by using the keypad shortcuts. Use this API to specify which special functions are to be assigned shortcuts on COSEC devices. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/function-key?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Function Key Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|F1<br>F2|0 = None<br>1 = Official IN<br>2 = Official OUT|||
|F3<br>F4|3 = Short Leave IN<br>4 = Short Leave OUT<br>5 = Regular IN<br>6 = Regular OUT<br>7 = Post Break IN<br>8 = Pre - Break OUT<br>9 = Overtime IN<br>10 = Overtime OUT|No|Assigning special functions to<br>respective function keys.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



##### Example 

**1. To configure function key F1 as official work – IN** _._ 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/function-key?action=set&f1=1 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <type> Content-Length: <length> Body: Response-Code=0 

Matrix COSEC Devices API User Guide 

17 

### **Reader Configuration** 

**Description:** To set or retrieve configuration parameters for internal and external readers such as reader type, access mode, entry-exit mode and the tag re-detection delay time. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/reader-config?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Reader Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|reader1|0 = None<br>1 = EM Prox Reader<br>2 = HID Prox Reader<br>3 = MiFare Reader<br>4 = HID iCLASS-U Reader<br>5 = HID iCLASS-W Reader|No|To define the internal card<br>reader.|
|reader2|0 = None<br>1 = Finger Reader<br>2 = Palm Vein Reader|No|To define the internal biometric<br>reader.|
|reader3|0 = None<br>1 = EM Prox Reader<br>2 = HID Prox Reader<br>3 = MiFare U Reader<br>4 = HID iCLASS-U Reader<br>5 = Finger Reader<br>6 = HID iCLASS-W Reader<br>7 = UHF Reader<br>8 = Combo Exit Reader<br>9 = MiFare-W Reader|No|To define the external reader.|
|door-access-mode|0 = Card<br>1 = Finger<br>2 = Card + PIN<br>3 = PIN + Finger<br>4 = Card + Finger<br>5 = Card + PIN + Finger<br>6 = Any<br>7 = Palm<br>8 = Palm + PIN<br>9 = Card + Palm<br>10 = Card + PIN + Palm<br>11 = Palm + Group (Optional)<br>12 =  Finger then Card<br>13 = Palm then Card|No|To define the access mode<br>applicable for door access.|
|door-entry-exit-mode|0 = Entry<br>1 = Exit|No|To define the whether the<br>internal reader is to be set on an<br>entry or exit mode.|



Matrix COSEC Devices API User Guide 

18 

**Table: Reader Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|reader-access-mode|0 = Card<br>1 = Finger<br>4 = Card + Finger<br>6 = Any<br>12 = Finger then Card|No|To define the access mode<br>applicable for the external<br>reader.|
|reader-entry-exit-mode|0 = Entry<br>1 = Exit|No|To define the whether the<br>external reader is to be set on an<br>entry or exit mode.|
|tag-re-detect-delay|00 - 3600 seconds|No|To define the tag re-detection<br>delay time.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



##### Example 

**1. To configure internal card reader as an HID Prox reader and internal reader mode as entry** . 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/reader-config?action=set&reader1=2&door-access-mode=0 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <type> Content-Length: <length> Body: Response-Code=0 

Matrix COSEC Devices API User Guide 

19 

### **Finger Reader Parameter Configuration** 

**Description:** To set the finger reader calibration for fingerprint enrollment. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi /finger-parameter?<argument>=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Finger Reader Parameter Configuration - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|security|0 = Normal<br>1 = Secure<br>2 = More Secure<br>Default = 0|Yes|To define the security type<br>while enrollment.|
|lighting-cond|0 = Out door<br>1 = In door<br>Default =1|No|To define the lighting condition.|
|sensitivity|0 = Level 1 (Low)<br>1 = Level 2<br>2 = Level 3<br>3 = Level 4<br>4 = Level 5<br>5 = Level 6<br>6 = Level 7<br>7 = Level 8 (High)<br>Default = 7|No|To define the sensitivity levels<br>from low to high.|
|fast-mode|0 = Mode 1 (Normal)<br>1 = Mode 2<br>2 = Mode 3<br>3 = Mode 4<br>4 = Mode 5<br>5 = Mode 6 (Fastest)<br>6 = Auto<br>Default = 6|No|To define the mode to be used<br>during enrollment.|
|image-quality|0 = Weak<br>1 = Moderate<br>2 = Strong<br>3 = Strongest<br>Default = 1|No|To define the acceptable<br>image quality for enrollment.|
|format|text,xml|No|Specifies the format in which<br>the response is expected|



Matrix COSEC Devices API User Guide 

20 

### **Palm Sensor Parameter Configuration** 

**Description:** To set the palm sensor calibration for palm enrollment. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi /palm-parameter?<argument>=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Finger Reader Parameter Configuration - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|security|0 = Normal<br>1 = Highest<br>2 = High<br>3 = Low<br>4 = Lowest<br>Default = 2|Yes|To define the security type<br>while enrollment.|
|palm-matching-<br>timeout|0 to 9999 sec<br>Default = 15 sec|No|To define the palm matching<br>timeout.|
|palm-temp-quality|0 = Good<br>1 = Moderate<br>2 = Poor<br>Default = 1|No|To define the acceptable<br>image quality for enrollment.|
|format|text,xml|No|Specifies the format in which<br>the response is expected|



Matrix COSEC Devices API User Guide 

21 

### **Enrollment Configuration** 

**Description:** To set or retrieve configuration parameters for enrollment of credentials on a device such as number of credentials allowed, number of templates allowed per finger, enrollment mode etc. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/enroll-options?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Enrollment Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|enroll-on-device|0 = Inactive<br>1 = Active|No|To enable/disable the feature to enroll<br>through special function|
|enroll-using|0 = User ID<br>1 = Reference No.|No|To define the option to enroll the credential<br>using the user’s Reference No. or User ID,<br>for enrollment through special function.<br>Note: This parameter will not be valid for<br>NGT Direct Door and Vega Controller<br>where enrollment must be performed by<br>User ID.|
|temp-per-finger|0 = Single Template/<br>Finger<br>1 = Dual Template/Finger|No|To define the number of templates to be<br>saved per finger.|
|enroll-finger-count|Single Template/Finger: 0-<br>9<br>where,<br>0 = 1 Finger<br>1 = 2 Fingers<br>2 = 3 Fingers<br>3 = 4 Fingers<br>4 = 5 Fingers<br>5 = 6 Fingers<br>6 = 7 Fingers<br>7= 8 Fingers<br>8 = 9 Fingers<br>9 = 10 Fingers<br>Dual Template/Finger: 0-4<br>where,<br>0 = 1 Finger<br>1 = 2 Fingers<br>2 = 3 Fingers<br>3 = 4 Fingers<br>4 = 5 Fingers|No|No. of fingers allowed to be enrolled in one<br>enrollment cycle.<br>Note: For the**action=set**method, this<br>value should not be greater than the**max-**<br>**finger**value set in Basic Device<br>Configuration API.|



Matrix COSEC Devices API User Guide 

22 

**Table: Enrollment Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|enroll-palm-count|0 = 1 Palm<br>1 = 2 Palms<br>2 = 3 Palms<br>3 = 4 Palms<br>4 = 5 Palms<br>5 = 6 Palms<br>6 = 7 Palms<br>7 = 8 Palms<br>8 = 9 Palms<br>9 = 10 Palms|No|No. of palms allowed to be enrolled in one<br>enrollment cycle.|
|enroll-card-count|0 = 1 Card<br>1 = 2 Cards<br>2 = 3 Cards<br>3 = 4 Cards|No|No. of special function cards allowed to be<br>enrolled in one enrollment cycle.|
|enroll-mode|0 = Read Only Card<br>1 = Smart Card<br>2 = Finger Print<br>3 = FP then Card<br>4 = Palm Template<br>5 = Palm then Card|No|To define the enrollment mode for<br>enrollment through device.|
|format|text,xml|No|Specifies the format in which the response<br>is expected.|





- _If the_ **_temp-per-finger_** _mode is changed, then the templates have to be restored to the device explicitly by the third party software, else mismatch will occur in the module._ 

- _If_ **_Single Template/Finger_** _mode is selected on the device and some users are already enrolled according to it and if abruptly the mode is changed to_ **_Dual Template/Finger_** _then:_ 

   - **i.** _If the maximum finger count was greater than 5 fingers in Single Template/Finger mode, then after changing the mode to the Dual Template/Finger, the finger count will set to 5._ 

   - **ii.** _If the maximum finger count was less than 5 fingers in Single Template/Finger mode, then after changing the mode to the Dual Template/Finger, the finger count will remain same._ 

- _If the mode is changed back to Single Template/Finger, then finger count should not be changed. If users want to increase the finger count they should mention it explicitly._ 

Matrix COSEC Devices API User Guide 

23 

### **Access Settings Configuration** 

**Description:** To set or retrieve configuration parameters for enabling basic access control on a device for users. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/access-setting?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Access Settings Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|week-day<0~6>|sun (0) to sat (6)<br>0 = Inactive<br>1 = Active|No|To define the active working days. This<br>parameter is repeated for each day of<br>the week.|
|work-start-hh|00-23|No|Define the work start time|
|work-start-mm|00-59|No|Define the work start time|
|work-end-hh|00-23|No|Define the work stop time|
|work-end-mm|00-59|No|Define the work stop time|
|format|text, xml|No|Specifies the format in which the<br>response is expected|



##### Example 

###### **1. To get data for all parameters in the text format.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/access-setting?action=get&format=xml 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <code> Content-Length: <type> Body: 

week-day0=1 week-day1=1 week-day2=1 week-day3=1 week-day4=1 week-day5=1 week-day6=1 work-start-hh=0 work-start-mm=0 work-end-hh=23 work-end-mm=59 

Matrix COSEC Devices API User Guide 

24 

### **Alarm Configuration** 

**Description:** To set or retrieve configuration parameters for enabling/disabling alarms and related functions on a COSEC device such as Auto Alarm Acknowledgement. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/alarm?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Alarm Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|alarm|0 = Inactive<br>1 = Active|No|To enable/disable alarm.|
|tamper-alarm|0 = Inactive<br>1 = Active|No|To enable or disable the feature.|
|auto-alarm-ack|0 = Inactive<br>1 = Active|No|To enable or disable the Auto Alarm<br>Acknowledgement feature.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



Matrix COSEC Devices API User Guide 

25 

### **Date and Time Configuration** 

**Description:** To set or retrieve date and time configurations on a COSEC device. The user can configure the date and time to be displayed on the device, the display format, the time update mode, the NTP server settings as well as the Daylight Savings Time (DST) settings on the selected device. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/date-time?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Date and Time Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|year|2009 to 2037|No|To set year value|
|month|01 to 12|No|To set month value|
|date|01 to 31|No|To set date|
|hour|00 to 23|No|To set hour|
|minute|00 to 59|No|To set minutes|
|second|00 to 59|No|To set seconds|
|time-format|0 = 24 hours<br>1 = 12 hours|No|Defines the time format to be<br>displayed on the device display.<br>Note: This is applicable only for the<br>time shown on the device display and<br>not for general date-time which will<br>always be in 24 hours format.|
|update-mode|0 = Auto<br>1 = Manual|No|Defines whether the update mode is<br>manual or through NTP Server.|
|ntp-server-type|0 = Predefined<br>1 = User Defined|No|Defines whether the NTP server is a<br>predefined server or user-defined<br>server address.|
|time-zone|00-74 (Tool supported by<br>Windows), default: GMT<br>(+05:30) Chennai, Kolkata,<br>Mumbai, New Delhi.<br>Refer to_“Table: Universal_<br>_Time Zone Reference” on_<br>_page 61_|No|To define the universal time zone.|
|ntp-server|0 = ntp1.cs.wisc.edu<br>1 = time.windows.com<br>2 = time.nist.gov|No|To define the NTP Address.|
|user-defined-ntp|Alphanumeric, Max. 40<br>characters.|No|To define the user-defined NTP.|
|dst-enable|0 = Disable<br>1 = Enable|No|To enable/disable DST.|



Matrix COSEC Devices API User Guide 

26 

**Table: Date and Time Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|fwd-month|0 = January<br>1 = February<br>2 = March<br>3 = April<br>4 = May<br>5 = June<br>6 = July<br>7 = August<br>8 = September<br>9 = October<br>10 = November<br>11 = December|No|Forward clock day|
|fwd-week|0 = 1st<br>1 = 2nd<br>2 = 3rd<br>3 = 4th<br>4 = Last|||
|fwd-day|0 = Sunday<br>1 = Monday<br>2 = Tuesday<br>3 = Wednesday<br>4 = Thursday<br>5 = Friday<br>6 = Saturday|||
|fwd-time-hh|00 - 23 (24 hours format<br>only)|No|Forward clock time instance|
|fwd-time-mm|00 - 59|||
|rev-month|0 = January<br>1 = February<br>2 = March<br>3 = April<br>4 = May<br>5 = June<br>6 = July<br>7 = August<br>8 = September<br>9 = October<br>10 = November<br>11 = December|No|Reverse clock day|
|rev-week|0 = 1st<br>1 = 2nd<br>2 = 3rd<br>3 = 4th<br>4 = Last|No||



Matrix COSEC Devices API User Guide 

27 

**Table: Date and Time Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|rev-day|0 = Sunday<br>1 = Monday<br>2 = Tuesday<br>3 = Wednesday<br>4 = Thursday<br>5 = Friday<br>6 = Saturday|No|Reverse clock day|
|rev-time-hh|00 - 23 (24 hours format<br>only)|No|Reverse clock time instance|
|rev-time-mm|00 - 59|||
|duration-hh|00 - 23 (24 hours format<br>only)|No|Time by which clock should be<br>forwarded or reversed.|
|duration-mm|00 - 59|||
|format|text,xml|No|Specifies the format in which the<br>response is expected.|





- _When user sets the time locally it should be GMT time. And in GET command also the time value to be returned will be GMT time irrespective of the time displaying on the device._ 

- _While configuring Daylight Saving Parameters, users are responsible to define the forward and reverse time properly._ 

Matrix COSEC Devices API User Guide 

28 

### **Door Features Configuration** 

**Description:** To enable, disable, define or retrieve configuration parameters related to various door features such as auto-relock, ASC, door sense, exit switch, greeting message display, voice guidance etc. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/door-feature?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Door Features Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|allow-exit-when-locked|0 = Inactive<br>1 = Active|No|To allow exit when door is locked.|
|auto-relock|0 = Inactive<br>1 = Active|No|To enable/disable the Auto-relock<br>feature.|
|asc-active|0 = Inactive<br>1 = Active|No|To enable/disable the Additional<br>Security Code (ASC).|
|buzzer-mute|0 = Unmute<br>1 = Mute|No|To mute/un-mute the buzzer.|
|door-sense-active|0 = Inactive<br>1 = Active|No|To enable/disable sensing of door<br>states.|
|door-sense|0 = NO<br>1 = NC|No|To define the normal door state as<br>as normally open (NO) or normally<br>closed (NC).|
|supervised|0 = Unsupervised<br>1 = Supervised|No|To enable/disable supervised<br>sensing of door states (four-state<br>monitoring of door controllers).|
|exit-switch|0 = Inactive<br>1 = Active|No|To enable/disable the exit switch.|
|greeting-msg-enable|0 = Inactive<br>1 = Active|No|To enable/disable the display<br>greeting message.|
|greeting-msg<1~4>|Alphanumeric, Max. 21<br>ASCII characters|No||
|greeting-start-time-<br>hh<1~4>|00-23|No|To define upto 4 display greeting|
|greeting-start-time-<br>mm<1~4>|00-59|No|<br>messages, the start time and the<br>end time for displaying each|
|greeting-end-time-<br>hh<1~4>|00-23|No|message.|
|greeting-end-time-<br>mm<1~4>|00-59|No||
|voice-guidance|0 = Inactive<br>1 = Active|No|To enable/disable Voice Guidance<br>(Only for NGT doors).|



Matrix COSEC Devices API User Guide 

29 

###### **Table: Door Features Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|





- _When greeting messages are defined in an order then first message will always have precedence over second and second over third and so on. Hence, if two messages defined with overlapped timing range, the first defined message between two will have the priority._ 

- _Third party should always take care of setting the time range for different messages._ 

Matrix COSEC Devices API User Guide 

30 

### **System Timers Configuration** 

**Description:** To set or retrieve configurations for the following system timers: 

|**Auto Alarm Acknowledgement Timer**|Specifies the time period in seconds after which an unacknowledged<br>alarm will acknowledge itself automatically.|
|---|---|
|**Inter Digit Wait Timer**|Specifies time period in seconds between two key inputs on the<br>device keypad. On the expiry of this timer, the system considers the<br>user input to be complete and is ready for the next input.|
|**Multi Access Wait Timer**|Defines the time in seconds for which the system needs to wait for<br>the second credential input from a user when more than one<br>credential is required to grant access.|
|**Palm Enrollment Time Out Timer**|Defines the time period in seconds within which a palm must be<br>enrolled after generating the enrollment command.|
|**Door Open Pulse Timer**|Defines the time in seconds required for a door to be energized for a<br>valid credential. If the opened door does not return to its closed state<br>before the expiry of this timer, the door will generate a “Door<br>Abnormal Alarm”.|
|**Special Function Timer**|Defines the time in minutes for which the Late-IN and Early-OUT special<br>functions will remain active after being enabled at the door controller.|



**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/system-timer?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: System Timers Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|alarm-ack-timer|10 to 65535 (sec)|No|To define the timer for Auto Alarm<br>Acknowledgement.|
|idwt|1-99 (sec)|No|To define the Inter Digit Wait Timer.|
|multi-access-wait-timer|3-99 (sec)|No|To define the Multi Access Wait Timer.|
|palm-enroll-time-out|3-99 (sec)|No|To define the Palm Enrollment Time<br>out Timer.|
|pulse-time|1 - 65535 (sec)|No|To define the Door Pulse time|
|sp-function-timer|1-99 (mins)|No|To define the Special Function Timer.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



Matrix COSEC Devices API User Guide 

31 

### **Special Function Configuration** 

**Description:** COSEC enables its users to perform certain pre-defined operations directly from the COSEC device. These are known as special functions. An RFID card can be encoded for a special function and the card-holder can perform this function at the device just by showing this special card. 

Use this API to enable, disable, define or retrieve Special Functions configuration on a device. 

**Actions:** get, set, getdefault, setdefault 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/special-function?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Special Function Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|Sp-fn-Index|1 = Offical Work - IN<br>2 = Official Work - OUT<br>3 = Short Leave - IN<br>4 = Short Leave - OUT<br>5 = Regular - IN<br>6 = Regular - OUT<br>7 = Post Break - IN<br>8 = Pre Break - OUT<br>9 = Over Time - IN<br>10 = Over Time - OUT<br>11 = Enroll User<br>12 = Enroll Special Card<br>13 = Delete Credentials<br>14 = Late IN - Start<br>15 = Late IN - Stop<br>16 = Early OUT - Start<br>17 = Early OUT- Stop<br>18 = Door Lock<br>19 = Door Unlock<br>20 = Door Normal<br>21 = Clear Alarm|Yes|The index number of a special function.|
|enable|0 = Disable<br>1 = Enable|No|To enable/disable special functions on<br>the device.|
|card1|64 Bits (20 Numeric Digits<br>approx.)|No|To define the special function card 1.|
|card2|64 Bits (20 Numeric Digits<br>approx.)|No|To define the special function card 2.|
|card3|64 Bits (20 Numeric Digits<br>approx.)|No|To define the special function card 3.|
|card4|64 Bits (20 Numeric Digits<br>approx.)|No|To define the special function card 4.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



Matrix COSEC Devices API User Guide 

32 

## **User Configuration** 

The various COSEC devices have capacity to support the following number of users: 

- Direct Door V2      :2000 

- NGT Direct Door   :10,000 

- Wireless Door       :50,000 

- Path Controller      :2000 

- PVR Door              :10,000 

- Vega Controller      :50,000 

This group of APIs enables users to add or delete users, set user photographs, add or fetch various configurations related to users on or from a device as well as synchronize credentials with device. The following functions can be called: 

- Setting/Retrieving User Configuration 

- Setting a User Photo 

- Deleting a User 

- Setting User Credentials 

- Retrieving User Credentials 

- Deleting User Credentials 

Matrix COSEC Devices API User Guide 

33 

### **Setting/Retrieving User Configuration** 

**Description:** To set basic user configuration parameters on a device using the **_action=set_** parameter and retrieve configuration details using **_action=get_** . 

###### **Actions:** get, set 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/users?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: User Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|user-id|Maximum 10 characters|Yes|To set or retrieve the<br>alphanumeric user ID for the<br>selected user.<br>Note: If a**_set_**request is sent<br>against an existing user ID, then<br>configuration for this user will be<br>updated with the new values.|
|user-index|Direct Door V2= 1 - 2,000<br>Path Controller = 1 - 2,000<br>Wireless Door = 1 - 50,000<br>PVR = 1 - 10,000<br>NGT = 1 - 10,000<br>Vega Controller = 1 - 50,000|No|To identify the index number for<br>the selected user ID (only**_get_**<br>parameter)|
|ref-user-id|Maximum 8 digits|Yes (Not<br>mandatory<br>for the**_get_**<br>action)|To select the numeric user ID on<br>which the specified operation is<br>to be done.|
|name|Alphanumeric. Max. 15 characters|No|To define the user name|
|user-active|0 = Inactive<br>1 = Active|No|to activate or deactivate a user.|
|vip|0 = Inactive<br>1 = Active|No|To define a user as VIP.<br>Note: A VIP user is a user with<br>the special privilege to access a<br>particular door.|
|validity-enable|0 = Inactive<br>1 = Active|No|To enable/disable the user<br>validity.|
|validity-date-dd|1-31|No||
|validity-date-mm|1-12|No|To define the end date for user<br>validity.|
|validity-date-yyyy|2000-2037|No||



Matrix COSEC Devices API User Guide 

34 

**Table: User Configuration Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|user-pin|1 to 6 Digits|No|To set the user PIN or get the<br>event from user PIN.<br>Note: The user-pin can be set to<br>a  blank value.|
|by-pass-finger|0 = Inactive<br>1 = Active|No|To enable/disable the bypass<br>finger option.|
|by-pass-palm|0 = Inactive<br>1 = Active|No|To enable/disable the bypass<br>palm option.|
|card1|64 Bits (8 bytes) (max value -<br>18446744073709551615)|No|To set or delete the card value<br>against a user.|
|card2|64 Bits (8 bytes) (max value -<br>18446744073709551615)|No|To set or delete the card value<br>against a user.|
|dob-enable|0 = Enable<br>1 = Disable|No|To enable/disable the display of a<br>birthday message.|
|dob-dd|1-31|||
|dob-mm|1-12|No|To set or delete the date of birth<br>for a user.|
|dob-yyyy|1990-2037|||
|user-group|0-999|No|To set the user group number.<br>**Note:**A user can be assigned to<br>any user group ranging from 1 to<br>999. User group number can be<br>set/update via “Set” command. To<br>remove a user from an assigned<br>user group, user group should be<br>set to 0.|
|format|text, xml|No|Specifies the format in which the<br>response is expected.|





- _For_ **_set_** _requests only one user’s complete data should be sent at a time. Attempting to set data for multiple users at a time will return an error response. For more examples of error responses, see Error Responses._ 

- _To create a new user on device, both_ **_user-id_** _and_ **_ref-user-id_** _are mandatory parameters to be provided, and these should be unique for each user._ 

- _If a user is already configured in the system and admin wants to update the user with new information/data, only Alphanumeric User ID is sufficient but if the reference user ID is also mentioned then it would be verified whether this belongs to the same user or not._ 

- _Whenever an event is generated related to a user, the required user ID field upon calling the event will always show user’s reference user ID. Whereas if “Get” action is sent to call user configuration then it will show alphanumeric user ID._ 

Matrix COSEC Devices API User Guide 

35 

##### Example 

###### **1. To get user names for user-id = 1** 

###### **Sample Request** 

http://deviceIP:deviceport/device.cgi/users?action=get&user-id=1&format=xml 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <xml> Content-Length: <length> Body: <COSEC_API> <user-id>1</user-id> <user-index>0</user-index> <ref-user-id></ref-user-id> <name></name> <user-active>0</user-active> <vip>0</vip> <validity-enable>0</validity-enable> <validity-date-dd>1</validity-date-dd> <validity-date-mm>1</validity-date-mm> <validity-date-yyyy>2009</validity-date-yyyy> <user-pin></user-pin> <by-pass-finger>0</by-pass-finger> <card1>0</card1> <card2>0</card2> </COSEC_API> 

Matrix COSEC Devices API User Guide 

36 

### **Setting a User Photo** 

**Description:** To set, fetch or delete a photograph against a user’s profile on the device using a third party application. 

**Actions:** get, set, delete 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/userphoto?action=<value>[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Setting a User Photo - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|user-id|Maximum 10 characters|Yes|To specify the alphanumeric user ID<br>for the user whose photo is to be set.|
|user-photo|N/A|Yes|To get, set or delete the user photo.<br>This should be done in the data<br>portion of the request /<br>response.(applicable only for VEGA<br>and NGT doors)|
|photo-format|0 = jpeg<br>1 = jpg<br>2 = png<br>3 = bmp|Yes (only for**_set_**<br>parameter)|To define the format for the<br>photograph.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



##### Example 

Following are some test cases for your reference: 

**1. To add an image file in .jpeg format for user-id 1.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/userphoto?action=set&user-id=1&photo-format=0 Data: Image data 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <code> Content-Length: <type> Body: Response-Code=0 

**2. To fetch the user photo for the same user.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/userphoto?action=get&user-id=1 

Matrix COSEC Devices API User Guide 

37 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: image/jpeg Content-Length: 12345 Body: _<JPEG Image Data>_ 

_This is an example only. The actual response will vary depending on product model and configuration._ 

Matrix COSEC Devices API User Guide 

38 

### **Deleting a User** 

**Description:** To delete a user from a device. Deleting a user will result in deletion of the credentials of that user along with all the other configurations set on the device. 

###### **Actions:** delete 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/users?action=delete[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Delete User - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|user-id|Maximum 10 characters|Yes|To specify the alphanumeric user ID<br>for the user to be deleted.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



Matrix COSEC Devices API User Guide 

39 

### **Setting User Credentials** 

**Description:** To set a user’s biometric or card credentials on a device. 

###### **Actions:** set 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/credential?action=set[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Setting User Credentials - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|type|1 = Finger<br>2 = Card<br>3 = Palm|Yes|To define the user credentials<br>type.|
|user-id|Alphanumeric (Max 10 characters)|Yes|To select the user-id for which the<br>credential is to be fetched.|
|card1|64 Bits (8 bytes) (max value -<br>18446744073709551615)|No|It defines the value for card-1|
|card2|64 Bits (8 bytes) (max value -<br>18446744073709551615)|No|It defines the value for card-2|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|
|data|-|No|This is the data of respective<br>credential type, which is to be<br>stored at given index number for<br>the respective user id.|



Matrix COSEC Devices API User Guide 

40 

### **Retrieving User Credentials** 

**Description:** To retrieve a user’s credential information from a device. 

###### **Actions:** get 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/credential?action=get[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Retrieving User Credentials - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|type|1 = Finger<br>2 = Card<br>3 = Palm|Yes|To define the user credentials<br>type.|
|user-id|Alphanumeric (Max. 10 characters|Yes|To select the user-id for which the<br>credential is to be fetched.|
|card1|64 Bits (8 bytes) (max value -<br>18446744073709551615)||It defines the value for card-1|
|card2|64 Bits (8 bytes) (max value -<br>18446744073709551615)||It defines the value for card-2|
|finger-index|1 = 1 Finger<br>2 = 2 Fingers<br>3 = 3 Fingers<br>4 = 4 Fingers<br>5 = 5 Fingers<br>6 = 6 Fingers<br>7 = 7 Fingers<br>8 = 8 Fingers<br>9 = 9 Fingers<br>10 = 10 Fingers|No|Identifies the number of finger<br>templates/palm templates to be<br>set or retrieved, on or from the<br>device The temlate will be set|
|palm-index|1 = 1 Palm<br>2 = 2 Palms<br>3 = 3 Palms<br>4 = 4 Palms<br>5 = 5 Palms<br>6 = 6 Palms<br>7 = 7 Palms<br>8 = 8 Palms<br>9 = 9 Palms<br>10 = 10 Palms|No|.  p<br>and retrieved from the data<br>portion of the request and<br>response.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|
|data|-|No|This is the data of respective<br>credential type, which is to be<br>stored at given index number for<br>the respective user id.|



Matrix COSEC Devices API User Guide 

41 



- _Credential parameters to be applied will depend on the credential type selected._ 

- _At a time only finger print or palm can be get/set. Both cannot be set at the same time._ 

- _The set command is basically similar to adding and duplication of finger template will not be verified by the device. It is expected to be handled by the 3rd party software._ 

- _The method used in this case should be POST method as it consists of raw/ hex data in the data portion of the request and the response._ 

- _Finger/palm index fields are not mentioned as mandatory fields because if user selects credential type card then there is no need to specify the finger or palm index, similarly if credential type is finger then palm index in not a mandatory field and vice versa._ 

Matrix COSEC Devices API User Guide 

42 

### **Deleting User Credentials** 

**Description:** To delete selected credentials of a user from a device. 

###### **Actions:** delete 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/credential?action=delete[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Deleting User Credentials - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|user-id|Alphanumeric (Max. 10 characters)|Yes|To delete the credential of a<br>particular user.|
|type|0 = All<br>1 = Finger<br>2 = Card<br>3 = Palm|Yes|Defines the credential type to be<br>deleted.<br>Note: For the selected type, all<br>credentials will be deleted.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



##### Example 

###### **1. To delete finger templates of user id 1.** 

###### **Sample Request** 

http://deviceIP:deviceport/device.cgi/credential?action=delete&user-id=1&type=1 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <type> Content-Length: <length> Body: Response-Code=0 

Matrix COSEC Devices API User Guide 

43 

## **Enrollment** 

The Enrollment APIs can be used to generate an enrollment request for a device. Once the enrollment request is successfully sent on the device, the device will initiate the enrollment process and request credentials to be provided physically, as per the credential type and sequence specified. 

Perform the enrollment function on a remote door controller using these enrollment APIs: 

- Enrolling a User 

- Enrolling Special Cards 

Matrix COSEC Devices API User Guide 

44 

### **Enrolling a User** 

**Description:** To command a device to initiate enrollment for a user based on parameters specified. 

###### **Actions:** enroll 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/enrolluser?action=enroll[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Enrolling User - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|type|0 = Read Only Card<br>1 = Smart Card<br>2 = Finger Print<br>3 = FP Then Card<br>4 = Palm Template<br>5 = Palm Then Card|Yes|Defines the credential to be<br>enrolled.|
|user-id|Maximum 10 characters|Yes|Defines the alphanumeric User ID<br>of the user whose credential is to<br>be enrolled.|
|finger-count|Single Template/Finger: 0-9<br>where,<br>0 = 1 Finger<br>1 = 2 Fingers<br>2 = 3 Fingers<br>3 = 4 Fingers<br>4 = 5 Fingers<br>5 = 6 Fingers<br>6 = 7 Fingers<br>7= 8 Fingers<br>8 = 9 Fingers<br>9 = 10 Fingers<br>Dual Template/Finger: 0-4<br>where,<br>0 = 1 Finger<br>1 = 2 Fingers<br>2 = 3 Fingers<br>3 = 4 Fingers<br>4 = 5 Fingers|No|To specify the number of fingers to<br>be enrolled.|
|card-count|0 = 1 Card<br>1 = 2 Cards<br>2 = 3 Cards<br>3 = 4 Cards|No|To specify the number of cards to<br>be enrolled.|



Matrix COSEC Devices API User Guide 

45 

**Table: Enrolling User - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|palm-count|0 = 1 Palm<br>1 = 2 Palms<br>2 = 3 Palms<br>3 = 4 Palms<br>4 = 5 Palms<br>5 = 6 Palms<br>6 = 7 Palms<br>7 = 8 Palms<br>8 = 9 Palms<br>9 = 10 Palms|No|To specify the number of palms to<br>be enrolled.|
|w-asc|0 = Inactive<br>1 = Active|No|To enable/disable the Additional<br>Security Code (ASC) to be written<br>on the Smart Card.|
|w-fc|0 = Inactive<br>1 = Active|No|To enable/disable the Facility Code<br>(FC) to be written on the Smart<br>Card.|
|w-ref-user-id|0 = Inactive<br>1 = Active|No|To enable/disable the User ID to be<br>written on the Smart Card.|
|w-name|0 = Inactive<br>1 = Active|No|To enable/disable the User Name to<br>be written on the Smart Card.|
|w-designation|0 = Inactive<br>1 = Active|No|To enable/disable the designation<br>to be written on the Smart Card.|
|w-branch|0 = Inactive<br>1 = Active|No|To enable/disable the branch name<br>to be written on the Smart Card.|
|w-department|0 = Inactive<br>1 = Active|No|To enable/disable the department<br>name to be written on the Smart<br>Card.|
|w-bg|0 = Inactive<br>1 = Active|No|To enable/disable the blood group<br>to be written on the Smart Card.|
|w-contact|0 = Inactive<br>1 = Active|No|To enable/disable Emergency<br>Contact information to be written on<br>the Smart Card.|
|w-medical-history|0 = Inactive<br>1 = Active|No|To enable/disable the medical<br>history to be written on the Smart<br>Card.|
|w-fp-template|0 = No Templates<br>1 = 1 Finger Template<br>2 = 2 Finger Templates|No|To enable/disable the finger<br>templates to be written on the<br>Smart Card.|
|name<br>designation<br>branch|Alphanumeric, 15 Chars, ASCII<br>Code|No|Defines the values for the<br>respective fields to be written on the<br>Smart Card.|
|department||||



Matrix COSEC Devices API User Guide 

46 

**Table: Enrolling User - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|bg|Maximum 4 characters. Valid<br>Values:<br>A+<br>A-<br>B+<br>B-<br>AB+<br>AB-<br>O+<br>O-<br>A1-<br>A1+<br>A1B-<br>A1B+<br>A2-<br>A2+<br>A2B-<br>A2B+<br>B1+|No|Defines the values for the<br>respective fields to be written on the<br>Smart Card.<br>Note: ‘**bg**’ stands for blood group of<br>the user.|
|contact|Alphanumeric, 15 Chars, ASCII<br>Code|No||
|medical-history|Alphanumeric, 15 Chars, ASCII<br>Code|No||
|format|text,xml|No|Specifies the format in which the<br>response is expected.|





- _This is only to send enrollment command, if the credential is to be retrieved then it has to be retrieved explicitly using the get and set credential command._ 

- _By default, if count is not specified for enroll command then consider it as one and perform the enroll operation._ 

- _This enrollment has no links to the parameter configured on the device for “enroll through special function”._ 

##### Example 

###### **1. To start enrollment of two fingers for user id 45.** 

###### **Sample Request** 

http://deviceIP:deviceport/device.cgi/enrolluser?action=enroll&user-id=45&type=2&finger-count=1 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <type> Content-Length: <length> Body: Response-Code=0 

Matrix COSEC Devices API User Guide 

47 

### **Enrolling Special Cards** 

**Description:** A Special Card is an RFID card which can be encoded for a special function. This API enables the user to perform enrollment of special cards on the selected device based on specified parameters such as special function ID and number of cards to be enrolled as special cards. 

###### **Actions:** enroll 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/enrollspcard?action=enroll[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

**Table: Enroll Special Cards - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|sp-fn-id|All configured Special Functions<br>(special function ID)|Yes|Defines the special function<br>identification number.|
|card-count|0 = 1 Card<br>1 = 2 Cards<br>2 = 3 Cards<br>3 = 4 Cards|No|To specify the number of cards to be<br>enrolled.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



Matrix COSEC Devices API User Guide 

48 

## **Events** 

Any action that occurs or is performed using a live COSEC device is referred on the COSEC system as an Event. A client application can directly request event logs to be fetched from a specific device or be fed with live events data via the device listening port. The functions available in this API group are as follows: 

- Retrieving Events 

- Retrieving Events in the TCP Socket 

Matrix COSEC Devices API User Guide 

49 

### **Retrieving Events** 

**Description:** To request all or specified events from a device. 

###### **Actions:** getevent 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/events?action=getevent[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Retrieving Events - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|roll-over-count|0 to 65535|Yes|This identifies the first event that is to<br>|
|seq-number|Refer to “Table: Value Range for<br>Event Sequence Numbers” for the<br>valid values on different devices.|Yes|be sent to the 3rd party from a set of<br>events sent in this response. If the<br>“no-of-events” field  value is 1, then<br>this will be the only event sent to the<br>server.|
|no-of-events|1 to 5 (for Direct Door V2 and Path<br>Controller)<br>1 to 100 ( for all other Direct Doors)|No|Specifies the number of events to be<br>fetched.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|



###### **Table: Value Range for Event Sequence Numbers** 

|**Door**|**Event Sequence Number**|
|---|---|
|V2|1 to 50,000|
|CDC|1 to 50,000|
|Wireless|1 to 5,00,000|
|NGT|1 to 1,00,000|
|PVR|1 to 1,00,000|
|Vega Controller|1 to 5,00,000|





- _For different kind of events, different fields are required, to understand the functionality of an event, which are denoted as detail fields._ 

- _The details field in the response depends on the type of device._ 

##### Example 

**1. To request specific events with roll over count = 0 and sequence number = 1. No. of events requested is 3, for an NGT door.** 

###### **Sample Request** 

http://deviceIP:deviceport/device.cgi/events?action=getevent&roll-over-count=0&seq-number=1&no-of-events=3 

Matrix COSEC Devices API User Guide 

50 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: xml Content-Length: 12345 Body: <COSEC_API> <Events> <roll-over-count>0</roll-over-count> <seq-No>1</seq-No> <date>16/4/2014</date> <time>14:56:20</time> <event-id>457</event-id> <detail-1>0</detail-1> <detail-2>0</detail-2> <detail-3>6</detail-3> <detail-4>0</detail-4> <detail-5>0</detail-5> </Events> <Events> <roll-over-count>0</roll-over-count> <seq-No>2</seq-No> <date>16/4/2014</date> <time>14:56:20</time> <event-id>453</event-id> <detail-1>0</detail-1> <detail-2>0</detail-2> <detail-3>0</detail-3> <detail-4>0</detail-4> <detail-5>0</detail-5> </Events> <Events> <roll-over-count>0</roll-over-count> <seq-No>3</seq-No> <date>16/4/2014</date> <time>14:57:28</time> <event-id>453</event-id> <detail-1>0</detail-1> <detail-2>0</detail-2> <detail-3>0</detail-3> <detail-4>0</detail-4> <detail-5>0</detail-5> </Events> </COSEC_API> 

For example if an enrollment event is called in which three fingers have been enrolled with the dual template per finger then the detail fields will be as follows: 

###### **For first finger:** 

- Event-ID: 405 (code for enrollment event) 

- Detail-1: user-id 

- Detail-2: 9 (code for finger credential) 

- Detail-3: **12** 

- Detail-4: 0 

- Detail-5 **:** 0 

###### **For second finger:** 

- Event-ID: 405 (code for enrollment event) 

- Detail-1: user-id 

- Detail-2: 9 (code for finger credential) 

- Detail-3: **24** 

- Detail-4: 0 

- Detail-5 **:** 0 

###### **For third finger:** 

- Event-ID: 405 (code for enrollment event) 

- Detail-1: user-id 

- Detail-2: 9 (code for finger credential) 

- Detail-3: **36** 

- Detail-4: 0 

- Detail-5 **:** 0 

If the template per finger mode was selected as single template per finger then the respective values for detail 3 will be 11, 22 and 33, where LSB denotes the template index. 

Matrix COSEC Devices API User Guide 

51 

### **Retrieving Events in the TCP Socket** 

**Description:** To receive all or specific events through the TCP listening port of the device. 

###### **Actions:** getevent 

**Syntax:** http://<deviceIP:deviceport>/device.cgi/tcp-events?action=getevent[&<argument>=<value>….] 

**Parameters:** All arguments for this query and their corresponding valid values are listed below: 

###### **Table: Retrieving Events in the TCP Socket - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|ipaddress<br>port|IP address and port number<br>validations are same as for network<br>configuration settings.|Yes|Defines the IP Address and the<br>listening port on which the events<br>are to be sent.|
|roll-over-count|0 to 65535|Yes|It is used to specify the exact<br>sequence number of an event stored<br>at any port.|
|seq-number|Refer to “Table: Value Range for<br>Event Sequence Numbers” for the<br>valid values on different devices.|Yes|It is used to specify the sequence<br>number of any event. The maximum<br>value for this can be from 1 to the<br>event log capacity of that device.|
|response-time|3 - 15 seconds|No|To specify the response time to wait<br>for a confirmation of established<br>network.|
|interface|0 = Ethernet<br>1 = Wi-Fi<br>2 = Mobile Broadband|No|Specifies the interface.<br>Note: If no interface is defined,<br>**Ethernet**will be tried by default.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|





_Due to memory constraints, this API is not supported on Direct Door V2._ 

##### Example 

**1. To request to send the events continuously on the TCP port from event seq 1 and roll over count 0 on IP address 192.168.102.42 and tcp listening port 80** **_._** 

###### **Sample Request** 

http://deviceIP:deviceport/device.cgi/tcp-events?action=getevent&ipaddress=192.168.102.42&port=80&roll-overcount=0&seq-number=1 

Matrix COSEC Devices API User Guide 

52 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <type> Content-Length: <length> Body: Response-Code=0 



- _The default TCP protocol acknowledgement should be used to send the next event. If in case any event is missed in between, then it is the responsibility of the 3rd party to re-request for that event. This shouldn’t be done via TCP port but missed events can be re-requested through HTTP API._ 

- _If during the event transferring if reboot occurs then the prior command (to send events) will no longer be valid and client must re-request events. In such a case, the events which have already been sent, will be overwritten by the same._ 

- _The user ID against which an event is stored must be the Reference ID for a user. This being numeric (max. 8 digits), will enable efficient utilization of storage space on devices, especially those having high event logging capacity (upto 5,00,000 events)._ 

Matrix COSEC Devices API User Guide 

53 

## **Sending Commands to Device** 

It is possible to send CGI commands to a device in order to perform certain functions. 

The generic URL for these commands: http://<deviceIP:deviceport>/device.cgi/command?action=<value> 

###### **Table: List of Commands to Device** 

|**S.No.**|**Command to Device**|**Action**|**Description**|
|---|---|---|---|
|1|Clear Alarm|clearalarm|To command the device to clear an<br>alarm.|
|2|Get Credential Count for<br>Enrolled Credentials|getcount|To get the count of already enrolled<br>templates and credentials for a user on<br>the selected device.|
|3|Acknowledge Alarm|acknoledgealarm|To command the device to acknowledge<br>an alarm without clearing it.|
|4|Lock Door|lockdoor|To command the door to return to a<br>locked state.|
|5|Unlock Door|unlockdoor|To command the door to return to an<br>unlocked state.|
|6|Normalize Door|normalizedoor|To command the door to return to a<br>normal state.|
|7|Get User Count on Device|getusercount|To obtain the total number of users<br>added on a device.|
|8|Get Current Event Sequence<br>Number|geteventcount|To get the current event sequence<br>number and roll over count in a device.|
|9|Default the System<br>Configuration|systemdefault|To set all the configurations on the<br>device to default status.|
|10|Delete Credentials for All Users|deletecredential|To delete all biometric credentials of<br>users from device.|



###### **For action=getcount** 

For valid values of this method, refer to the following argument-value table. 

**Table: Get Credential Count Command - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|user-id|1 to max. User ID in the door<br>(2 bytes)|Yes|Defines the numeric ID of the user<br>whose data is to be fetched.|
|card-count|0 = 1 Card<br>1 = 2 Cards<br>2 = 3 Cards<br>3 = 4 Cards|No|To get the number of cards enrolled.|



Matrix COSEC Devices API User Guide 

54 

**Table: Get Credential Count Command - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|finger-count|Single Template/Finger: 0-9<br>0 = 1 Finger<br>1 = 2 Fingers<br>2 = 3 Fingers<br>3 = 4 Fingers<br>4 = 5 Fingers<br>5 = 6 Fingers<br>6 = 7 Fingers<br>7 = 8 Fingers<br>8 = 9 Fingers<br>9= 10 Fingers<br>Dual Template/Finger: 0-4<br>0 = 1 Finger<br>1 = 2 Fingers<br>2 = 3 Fingers<br>3 = 4 Fingers<br>4 = 5 Fingers|No|To get the number of fingers<br>enrolled.|
|palm-count|0 = 1 Palm<br>1 = 2 Palms<br>2 = 3 Palms<br>3 = 4 Palms<br>4 = 5 Palms<br>5 = 6 Palms<br>6 = 7 Palms<br>7 = 8 Palms<br>8 = 9 Palms<br>9 = 10 Palms|No|To get the number of palms enrolled.|
|format|text,xml|No|Specifies the format in which the<br>response is expected.|





- _If no parameter is requested then all the count values will be returned by default (of supported credential types e.g. for PVR door, only card and palm template count will be returned)._ 

- _Palm template count and finger template counts depend on the device type i.e. Palm template count is only applicable for PVR doors and FP template counts are applicable for other devices. The specified credential should be applicable for the device on which the command is sent._ 

Matrix COSEC Devices API User Guide 

55 

###### **For action=deletecredential** 

For valid values of this method, refer to the following argument-value table. 

###### **Table: Deleting Credentials for All Users - Parameters** 

|**Argument**|**Valid Values**|**Mandatory**|**Description**|
|---|---|---|---|
|type|0 = All<br>1 = Finger<br>2 = Palm|Yes|To specify the type of credential to<br>be deleted.|



##### Example 

Following are some test cases for your reference: 

**1. To get the current rollover count and sequence number of events in the device.** 

###### **Sample Request** 

http://<deviceIP:deviceport>/device.cgi/command?action=geteventcount&format=xml 

###### **Sample Response** 

HTTP Code: 200 OK Content-Type: <xml> Body: <COSEC_API> <Roll-over-count>1</roll-over-count> <seq-number> _1_ </seq-number> 

</COSEC_API > 

Matrix COSEC Devices API User Guide 

56 

## **Error Responses** 

These are some possible error response types obtained from incorrect API requests. 

- **Argument is mentioned in request but valid value is not assigned.** 

###### **Sample Response** 

HTTP code: <code> Content-type: <type> Body: Request failed: Incomplete command “<argument>=” 

- **Invalid value is assigned to argument in request.** 

###### **Sample Response** 

HTTP code: <code> Content-type: <type> Body: Request failed: Invalid command “<argument>=<invalid value>” 

- **Syntax of request is incorrect or any unexpected arguments are received.** 

###### **Sample Response** 

HTTP code: <code> Content-type: <type> Body: Request failed: Invalid syntax “<entire request>” 

- **Mandatory fields are not mentioned in request.** 

###### **Sample Response** 

HTTP code: <code> Content-type: <type> Body: Request failed: Incomplete command “<entire request>” 

Matrix COSEC Devices API User Guide 

57 

- **Syntax of request is valid but no data found** **_._** 

###### **Sample Response** 

HTTP code: <code> Content-type: <type> Body: Request failed: No record found “<argument>=<value>” 

Matrix COSEC Devices API User Guide 

58 

## **API Response Codes** 

These numerical codes will be returned with an API response. These response codes shall indicate the result of a particular request made by the client. For e.g. the response code ‘0’ will indicate that the requested action was performed successfully. Refer to the given table for a list of response codes and their meanings. 

###### **Table: API Response Codes** 

|**Response Code**|**Description**|**Test Condition**|
|---|---|---|
|0|Successful|-|
|1|Failed - Invalid Login<br>Credentials|On every Authentication/Verification while logging In|
|2|Date and time – manual set<br>failed|If unable to set the RTC for date and time API|
|3|Invalid Date/Time|In User API, if validity-date or date of birth is set wrong.<br>If the starting time and end time of a shift is configured as same.|
|4|Maximum users are already<br>configured.|On every set command for user API|
|5|Image – size is too big|On every set command for user API|
|6|Image – format not<br>supported|On every set command for user API|
|7|Card 1 and card 2 are<br>identical|On every set command for user API and set credential API|
|8|Card ID exists|On every set command for user API and set credential API, Set<br>Special Function API|
|9|Finger print template/ Palm<br>template already exists|Set credential API|
|10|No Record Found|Event sequence number and roll over count not found, user id not<br>found in Set User API|
|11|Template size/ format<br>mismatch|If the expected template size is not as per the required size, format or<br>any checksum error etc. in Set credential API|
|12|FP Memory full|In Set credential API, if the max FP template is set in the module.|
|13|User id not found|In enroll user command if user id is not available in the device and in<br>User Configuration API, to update a user if provided reference user ID<br>doesn’t belong to that user verified with alphanumeric user ID.|
|14|Credential limit reached|In enroll user command, if max no. of credentials is already enrolled.|
|15|Reader mismatch/ Reader<br>not configured|The enroll request is for smart card and the device has proximity<br>reader or if enroll request has palm template but door has finger reader<br>and similar cases.|
|16|Device Busy|All cases of enrollment when the device is unable to process a request<br>as it is in a different menu state|
|17|Internal process error|Internal error like configuration, firmware or event or calibration failure<br>occur|
|18|PIN already exists|Set User API: PIN is already assigned to another user|
|19|Biometric credential not<br>found|In enroll user smart card, write FP is enabled, but FP is not enrolled,<br>Get FP/Palm template command is sent but template is not present.|
|20|Memory Card Not Found|In case memory card is not connected, and a command related to<br>getting an image (user photo) is sent.|



Matrix COSEC Devices API User Guide 

59 

###### **Table: API Response Codes** 

|**Response Code**|**Description**|**Test Condition**|
|---|---|---|
|21|Reference User ID exists|When an already existing User ID is entered against a user having<br>unique User ID.|
|22|Wrong Selection|For enrolling user, if writing FP template on smart card is enabled, but<br>no fingerprint is enrolled.<br>When palm/finger/card count exceeds the maximum number of<br>available places.|



Matrix COSEC Devices API User Guide 

60 

## **Appendix** 

###### **Table: Universal Time Zone Reference** 



|Index=0|Text="(GMT-12:00)International Date Line West"|
|---|---|
|Index=1|Text="(GMT-11:00)MidwayIsland,Samoa"|
|Index=2|Text="(GMT-10:00)Hawaii"|
|Index=3|Text="(GMT-09:00)Alaska"|
|Index=4|Text="(GMT-08:00)Pacific Time(Us & Canada);Tijuana"|
|Index=5|Text="(GMT-07:00)Arizona"<br>|
|Index=6|Text="(GMT-07:00)Chihuahua,La Paz,Mazatlan"|
|Index=7|Text="(GMT-07:00)Mountain Time(Us & Canada)"|
|Index=8|Text="(GMT-06:00)Central America"|
|Index=9|Text="(GMT-06:00)Central Time(Us & Canada)"|
|Index=10|Text="(GMT-06:00)Guadalajara,Mexico City,Monterrey"|
|Index=11<br>|Text="(GMT-06:00)Saskatchewan"<br>"  "|
|Index=12|Text=(GMT-05:00)Bogota,Lima, Quito|
|Index=13|Text="(GMT-05:00)Eastern Time(Us & Canada)"|
|Index=14|Text="(GMT-05:00)Indiana(East)"|
|Index=15|Text="(GMT-04:00)Atlantic Time(Canada)"|
|Index=16|Text="(GMT-04:00)Caracas,La Paz"|
|Index=17|Text="(GMT-04:00)Santiago"|
|Index=18|Text="(GMT-03:30)Newfoundland"|
|Index=19|Text="(GMT-03:00)Brasilia"<br>|
|Index=20|Text="(GMT-03:00)Buenos-Aires,Georgetown"|
|Index=21|Text="(GMT-03:00)Greenland"|
|Index=22<br>|Text="(GMT-02:00)Mid-Atlantic"<br>|
|Index=23|Text="(GMT-01:00)Azores"|
|Index=24|Text="(GMT-01:00)Cape Verde Is"|
|Index=25|Text="(GMT)CASABLANCA,MONROVIA"|
|Index=26|Text="(GMT)Dublin,Edinburgh,Lisbon,London"|
|Index=27|Text="(GMT+01:00)Amsterdam,Berlin,Bern,Rome,Stockholm,Vienna"<br>|
|Index=28|Text="(GMT+01:00)Belgrade,Bratislava,Budapest,Ljubljana,Prague"|
|Index=29|Text="(GMT+01:00)Brussels,Copenhagen,Madrid,Paris"|
|Index=30|Text="(GMT+01:00)Sarajevo,Skopje,Warsaw,Zagreb"|
|Index=31|Text="(GMT+01:00)West Central Africa"|
|Index=32|Text="(GMT+02:00)Athens,Beirut,Istanbul,Minsk"<br>|
|Index=33|Text="(GMT+02:00)Bucharest"|
|Index=34|Text="(GMT+02:00)Cairo"|
|Index=35|Text="(GMT+02:00)HararePretoria"|
|Index=36|, <br>Text="(GMT+02:00)Helsinki,Kyiv,Riga,Sofia,Tallinn,Vilnius"|
|Index=37|Text="(GMT+02:00)Jerusalem"<br>|
|Index=38|Text="(GMT+03:00)Baghdad"|
|Index=39|Text="(GMT+03:00)Kuwait,Riyadh"|
|Index=40|Text="(GMT+03:00)Moscow,St Petersburg,Volgograd"<br>|
|Index=41|Text="(GMT+03:00)Nairobi"|
|Index=42|Text="(GMT+03:30)Tehran"|
|Index=43|Text="(GMT+04:00)Abu Dhabi,Muscat"|
|Index=44|Text="(GMT+04:00)Baku,Tbilisi,Yerevan"|
|Index=45|Text="(GMT+04:30)Kabul"<br>|
|Index=46|Text="(GMT+05:00)Ekaterinburg"|
|Index=47|Text="(GMT+05:00)Islamabad,Karachi,Tashkent"|
|Index=48|Text="(GMT+05:30)Chennai,Kolkata,New Delhi,Mumbai"|
|Index=49|Text="(GMT+05:45)Kathmandu"|
|Index=50|Text="(GMT+06:00)Almay,Novosibirsk"|
|Index=51|Text="(GMT+06:00)Astana,Dhaka"|
|Index=52|Text="(GMT+06:00)Sri Jayewardenepura"|
|Index=53|Text="(GMT+06:30)Rangoon"|
|Index=54|<br>Text="(GMT+07:00)Bangkok,Hanoi,Jakarta"|
|Index=55|Text="(GMT+07:00)Krasnoyarsk"|
|Index=56|Text="(GMT+08:00)Beijing,Chongqing,HongKong,Urumqi"|
|Index=57|Text="(GMT+08:00)Irkutsk,Ulaanbataar"|
|Index=58|Text="(GMT+08:00)Kuala Lumpur,Singapore"|
|Index=59|Text="(GMT+08:00)Perth"|
|Index=60|Text="(GMT+08:00)Taipei"|



Matrix COSEC Devices API User Guide 

61 





**Table: List of Events** 

|**Event ID**|**Event Description**|
|---|---|
|163|User Denied – Invalid Access Group|
|164|User Denied – Validity date expired|
|165|User Denied – Invalid Route Access|
|166|User Denied – Invalid Shift Access|
|201|Door Status changed|
|202|Dead-man timer changed|
|203|DND status changed|
|204|Aux input status changed|
|205|Aux output status changed|
|206|Door sense input status|
|207|Door Controller Communication status|
|301|Dead-man timer expired Alarm– User IN|
|302|Duress detection|
|303|Panic Alarm|
|304|FP Memory Full – Alarm|
|305|Door Held open too long|
|306|Door Abnormal|
|307|Door force open|
|308|Door Controller Offline|
|309|Door Controller -Fault|
|310|Tamper Alarm|
|311|Master Controller Mains fail Alarm|
|312|Master Controller Battery fail|
|313|Master Alarm – MC Alarm input|
|314|RTC|
|315|Event Buffer Full|
|351|Alarm acknowledged|
|352|Alarm cleared|
|353|Alarm Re-issued|
|401|User Block/Restore|
|402|Login to ACS|
|403|Message transaction confirmation to ACMS|
|404|Guard Tour-status|
|405|Enrolment|
|406|Master Alarm sense input status|
|407|Master Aux Output status|



Matrix COSEC Devices API User Guide 

63 

**Table: List of Events** 

|**Event ID**|**Event Description**|
|---|---|
|408|Input Output Group Link status|
|409|Credentials Deleted|
|410|Time Triggered Function|
|411|Time Stamping Function|
|412|Guard tag|
|413|Camera Event for time stamp|
|451|Configuration Change|
|452|Roll over of events|
|453|Master Controller Power ON|
|454|Configuration Defaulted|
|455|Soft Override|
|456|Backup and Update|
|457|Default System|
|458|Sensor Calibration|





_Some of the events listed are applicable only on Panels/Panel Doors and not on Direct Doors. Refer the respective event tables to see the applicable doors for each event._ 

###### **Table: Size of Event Fields** 

|**Door**|**Field 1**|**Field 2**|**Field 3**|**Field 4**|**Field 5**|**Event Log Capacity**|
|---|---|---|---|---|---|---|
|Direct Door V2|4 bytes|2 bytes|2 bytes|N.A.|N.A.|50,000 events|
|Path Controller|4 bytes|2 bytes|2 bytes|N.A.|N.A.|50,000 events|
|Wireless Door|4 bytes|2 bytes|2 bytes|4 bytes|4 bytes|5,00,000 events|
|NGT Direct Door|4 bytes|2 bytes|2 bytes|4 bytes|4 bytes|1,00,000 events|
|PVR Door|4 bytes|2 bytes|2 bytes|4 bytes|4 bytes|1,00,000 events|
|Vega Controller|4 bytes|2 bytes|2 bytes|4 bytes|4 bytes|5,00,000 events|



###### **Table: User Events** 

|**Event ID**|**(Field 1)**<br>**User ID**|**Event De**<br>**(Field 2)**<br>**Special**<br>**Code**|**tails**<br>**(Field 3)**<br>**Entry/Exit**|**(Field 4)**<br>Us|**(Field 5)**<br>er Allowed E|**Direct**<br>**Door V2**<br>vents|**Path**<br>**Controller**|**Applicabl**<br>**Wireless**<br>**Door**|**e Devices**<br>**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|---|---|---|---|---|---|---|---|---|---|---|---|



Matrix COSEC Devices API User Guide 

64 

###### **Table: User Events** 

|||**Event De**|**tails**|||||**Applicab**|**le Devices**|||
|---|---|---|---|---|---|---|---|---|---|---|---|
|**Event ID**|**(Field 1)**<br>**User ID**|**(Field 2)**<br>**Special**<br>**Code**|**(Field 3)**<br>**Entry/Exit**|**(Field 4)**|**(Field 5)**|**Direct**<br>**Door V2**|**Path**<br>**Controller**|**Wireless**<br>**Door**|**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|101|Xxxx<br>(user ID=0 for<br>REX input)|Special<br>Function<br>code|Detail|0|0|||||||
|102|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|103|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|104|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|105|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|106|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|107|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|108|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|109|Xxxx|Special<br>Function<br>code|Detail|0|0|||||||
|110|Xxxx|Special<br>Function<br>|Detail|0|0|||||||
|||code<br>Special||U|ser Denied E|vents||||||
|151|(User ID = 0 if<br>not identified)|<br>Function<br>code|Detail|0|0|||||||
|152|Xxxx|0|Detail|0|0|||||||
|153|Xxxx|0|Detail|0|0|||||||
|154|Xxxx|0|Detail|0|0|||||||
|155|Xxxx|0|Detail|0|0|||||||
|156|Xxxx|0|Detail|0|0|||||||
|157|Xxxx|0|Detail|0|0|||||||
|158|Xxxx|0|Detail|0|0|||||||
|159|Xxxx|0|Detail|0|0|||||||
|160|Xxxx|0|Detail|0|0|||||||
|161|Xxxx|0|Detail|0|0|||||||
|162|Xxxx|0|Detail|0|0|||||||
|163|Xxxx|0|Detail|0|0|||||||
|164|Xxxx|0|Detail|||||||||



Matrix COSEC Devices API User Guide 

65 

**Table: User Events** 

||**(Field 1)**|**Event Deta**<br>**(Field 2)**|**ils**<br>**(Field 3)**|**(Field 4)**|**(Field 5)**|||**Applicab**|**le Devices**|||
|---|---|---|---|---|---|---|---|---|---|---|---|
|**Event ID**|**User ID**|**Special**<br>**Code**|**Entry/Exit**|||**Direct**<br>**Door V2**|**Path**<br>**Controller**|**Wireless**<br>**Door**|**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|||0=Door Not<br>in Sequence<br>1=Door Not<br>in Route||||||||||
|165|Xxxx|2=Door Not<br>in Sequence<br>for Smart<br>card based<br>Route<br>3=Door Not<br>in Smart<br>card based<br>Route<br>4=Credentia<br>l Invalid for<br>Smart card<br>based<br>Route<br>Access|Detail|0|0|||||||
|||0=Outside<br>working<br>hours<br>1=Holiday||||||||||
|166|Xxxx|2=Week off|Detail|0|0|||||||
|||3=Field<br>Break<br>4=Rest Day||||||||||



**Table: Special Function Codes Reference** 

|**S.No.**|**Special Function Name**|**Special Function Code**|**Applicable for Allowed**<br>**Events**|**Applicable for Denied**<br>**Events**|
|---|---|---|---|---|
|1|Official Work-IN Marking in<br>T&A|1|||
|2|Official Work-OUT Marking<br>in T&A|2|||
|3|Short Leave-IN Marking in<br>T&A|3|||
|4|Short Leave-OUT Marking<br>in T&A|4|||
|5|Clock - IN Marking in T&A|5|||
|6|Clock - OUT Marking in<br>T&A|6|||
|7|Post Lunch-IN Marking in<br>T&A|7|||



Matrix COSEC Devices API User Guide 

66 

###### **Table: Special Function Codes Reference** 

|**S.No.**|**Special Function Name**|**Special Function Code**|**Applicable for Allowed**<br>**Events**|**Applicable for Denied**<br>**Events**|
|---|---|---|---|---|
|8|Pre Lunch -OUT Marking<br>in T&A|8|||
|9|Over time – IN Marking in<br>T&A|9|||
|10|Over time – OUT Marking<br>in T&A|10|||
|11|Late –IN Allowed Marking<br>in T&A|11|||
|12|Early - OUT Allowed<br>Marking in T&A|12|||
|13|Access in Degrade Mode<br>Marking|99|||
|14|Smart Identification|98|||
|15|e-Canteen|97|||



###### **Table: Field 3 Detail (User Events) Reference** 

|Bit 15|Bit 14|Bit 13|Bit 12|Bit 11|Bit 10|Bit 9|Bit 8|Bit 7|Bit 6|Bit 5|Bit 4|Bit 3<br>Bit 2|Bit 1|Bit 0|
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
||||RFU||||Group|Palm|Finger|Card|PIN|RFU|RFU|Entry/<br>Exit|



###### **Table: Information of Bit 0 and Bit 1** 

|**Credential**|**Bit 1**|**Bit 0**|**Value**||
|---|---|---|---|---|
|Entry|0|0|0||
|Exit|0|1|1||
|Entry with Time Stamp Active|1|0|2||
|Exit with Time Stamp Active|1|1|3||



###### **Table: Information of Bit 4 and Bit 8** 

|**Credential**|**Bit 8**|**Bit 7**|**Bit 6**|**Bit 5**|**Bit 4**|**Value**|
|---|---|---|---|---|---|---|
|PIN|0|0|0|0|1|1|
|Card|0|0|0|1|0|2|
|Card + PIN|0|0|0|1|1|3|
|Finger|0|0|1|0|0|4|



Matrix COSEC Devices API User Guide 

67 

**Table: Information of Bit 4 and Bit 8** 

|**Credential**|**Bit 8**|**Bit 7**|**Bit 6**|**Bit 5**|**Bit 4**|**Value**|
|---|---|---|---|---|---|---|
|Finger + PIN|0|0|1|0|1|5|
|Finger + Card|0|0|1|1|0|6|
|Finger + Card + PIN|0|0|1|1|1|7|
|Finger + Card|0|0|1|1|0|6|
|Finger + Card + PIN|0|0|1|1|1|7|
|Finger + Card|0|0|1|1|0|6|
|Finger + Card + PIN|0|0|1|1|1|7|
|Palm|0|1|0|0|0|8|
|PIN + Palm|0|1|0|0|1|9|
|Card + Palm|0|1|0|1|0|10|
|PIN + Card + Palm|0|1|0|1|1|11|
|Group + Palm|1|1|0|0|0|24|



###### **Table: Door Events** 

||**(Field 1)**|**Event De**<br>**(Field 2)**|**tails**<br>**(Field 3)**|**(Field 4)**|**(Field 5)**|||**Applicab**|**le Devices**|||
|---|---|---|---|---|---|---|---|---|---|---|---|
|**Event ID**|**Status**|||||**Direct**<br>**Door V2**|**Path**<br>**Controller**|**Wireless**<br>**Door**|**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|201|1= Normal<br>2= Locked<br>3= Unlocked|0|0|0|0|||||||
|202|4= Activated<br>5= Deactivated|0|0|0|0|||||||
|203|4= Activated<br>5= Deactivated|0|0|0|0|||||||
|204|4= Activated<br>1= Normal<br>6= Fault (open)<br>7= Fault (short)<br>11= Disabled|0|0|0|0|||||||
|205|4= Activated<br>1= Normal<br>11= Disabled|0|0|0|0|||||||
|206|1= Normal<br>6= Fault (open)<br>7= Fault (short)<br>11= Disabled|0|0|0|0|||||||
|207|0|0|1= ON Line<br>0= OFF Line|0|0|||||||



Matrix COSEC Devices API User Guide 

68 

###### **Table: Alarm Events** 

|||**Event Details**||||||**Applicab**|**le Devices**|||
|---|---|---|---|---|---|---|---|---|---|---|---|
|**Event ID**|**(Field 1)**|**(Field 2)**|**(Field 3)**|**(Field**<br>**4)**|**(Field**<br>**5)**|**Direct**<br>**Door V2**|**Path**<br>**Controller**|**Wireless**<br>**Door**|**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|301|User ID Xxxx|1 = Critical|Alarm<br>Seque-<br>nce<br>Number|0|0|||||||
|302|User ID Xxxx|1 = Critical|Same as<br>above|0|0|||||||
|303|User ID Xxxx|1 = Critical|Same as<br>above|0|0|||||||
|304|1= Internal<br>2= External|3 = Minor|Same as<br>above|0|0|||||||
|305|0|3 = Minor|Same as<br>above|0|0|||||||
|306|0|2 = Major|Same as<br>above|0|0|||||||
|307|0|1 = Critical|Same as<br>above|0|0|||||||
|308|0|2 = Major|Same as<br>above|0|0|||||||
|309|0|2 = Major|Same as<br>above|0|0|||||||
|310|0|1 = Critical|Same as<br>above|0|0|||||||
|311|0|2 = Major|Same as<br>above|0|0|||||||
|312|0|1 = Critical|Same as<br>above|0|0|||||||
|313|0|1 = Critical|Same as<br>above|0|0|||||||
|314|1= Power ON/<br>OFF Detected<br>(time not in<br>sync)<br>2= low battery<br>detected<br>3= RTC Not<br>Detected|2 = Major<br>1 = Critical|Same as<br>above|0|0|||||||
|315|0|2 = Major<br>1 = Critical|Same as<br>above|0|0|||||||
|351|0|4 = SysInterlock<br>5 = User_Jeeves<br>6 = User_ACMS<br>9 = Auto|Same as<br>above|0|0|||||||
|352|0|4 = SysInterlock<br>5 = User_Jeeves<br>6 = User_ACMS<br>7= Special<br>Function|Same as<br>above|0|0|||||||
|353|0|0|Same as<br>above|0|0|||||||



Matrix COSEC Devices API User Guide 

69 

###### **Table: System Events** 

|||**Event Det**|**ails**|||||**Applicab**|**le Devices**|||
|---|---|---|---|---|---|---|---|---|---|---|---|
|**Event**<br>**ID**|**(Field 1)**|**(Field 2)**|**(Field 3)**|**(Field**<br>**4)**|**(Field**<br>**5)**|**Direct**<br>**Door V2**|**Path**<br>**Controller**|**Wireless**<br>**Door**|**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|401|User ID:<br>xxxx|0= Unused<br>(Restore User)<br>1=Absentee Rule<br>2=Unauthorized<br>access<br>3=Usage count<br>4=Invalid PIN|1= Blocked<br>0= Restored|0|0|||||||
|402|0|5= SA<br>6= SE<br>7= Operator|1=Success<br>0=Fail|0|0|||||||
|403|Transaction<br>ID: Xxxx|0|1=Success<br>0=Fail|0|0|||||||
|404|Guard Tour<br>no. Xxxx +<br>cycle no.|0|1=Success<br>0=Fail|0|0|||||||
|405|ID: Xxxx|8 = User Card<br>9 = User Finger<br>10 = Special<br>Cards<br>14 = Palm|1= Card/FP/<br>Palm-1<br>2= Card/FP/<br>Palm-2<br>3 = Card-3<br>4 = Card-4|0|0|||||||
|406|0|0|1=Normal<br>2=Fault (Open)<br>3= Fault(Short)<br>4= Activated|0|0|||||||
|407|0|0|1=Normal<br>4=Activated|0|0|||||||
|408|I/O Link ID|11 = Pulse<br>12 = Interlock<br>13 = Latch<br>15 = Toggle<br>(only with<br>activated event)|1=Normal<br>4=Activated|0|0|||||||
|409|ID: Xxxx|8 = User Cards<br>9 = User Fingers<br>14 = Palm|5= Web Jeeves<br>6= ACMS<br>7= Special<br>Function|0|0|||||||
|410|Time<br>Triggered<br>Function Id|0|1=Normal/<br>Deactivated<br>4=Activated|0|0|||||||
|411|Time<br>Stamping<br>Function ID|0|1=Normal/<br>Deactivated<br>4=Activated|0|0|||||||
|412|Guard tour<br>no. +cycle<br>no.|Door Controller<br>sequence no.|1=Success<br>0=Fail|0|0|||||||
|413|event<br>sequence<br>number|roll over count|1=Success<br>0=Fail|0|0|||||||
|451|Configur-<br>ation Table<br>ID  xxx|Index start|Index end|0|0|||||||



Matrix COSEC Devices API User Guide 

70 

###### **Table: System Events** 

|||**Event Det**|**ails**|||||**Applicab**<br>|**le Devices**<br>|||
|---|---|---|---|---|---|---|---|---|---|---|---|
|**Event**<br>**ID**|**(Field 1)**|**(Field 2)**|**(Field 3)**|**(Field**<br>**4)**|**(Field**<br>**5)**|**Direct**<br>**Door V2**|**Path**<br>**Controller**|**Wireless**<br>**Door**|**NGT**<br>**Door**|**PVR**<br>**Door**|**Vega**<br>**Controller**|
|452|Roll over<br>number<br>00 to 99|0|0|0|0|||||||
|453|0|0|0|0|0|||||||
|454|Configur-<br>ation Table<br>ID  xxx|Index start|Index end|0|0|||||||
|455|Time Period<br>= xxx<br>(configured<br>value)<br>(this field is<br>used only<br>with<br>Overridden<br>events)<br>Resume<br>events will<br>have blank|1= 2-person Rule<br>2= Access<br>Policies<br>3= Alarms<br>4= Anti-pass<br>back<br>5= First In User<br>6= Mantrap<br>7= Occupancy<br>control<br>8= Visitor Escort<br>Rule|1= Overridden<br>0= Resumed|0|0|||||||
|456|1=Backup<br>2=Update|1=Configuration<br>2=Event<br>3=Firmware|0 = Fail<br>1 = Success<br>2 = CRC Check<br>Fail|0|0|||||||
|457|0|0|6 = from ACMS<br>8 = from<br>Hardware|0|0|||||||
|458|0|0 = Internal<br>Finger Reader<br>1 = External<br>Finger Reader|0 = Fail<br>1 = Success<br>2 = Not<br>Supported|0|0|||||||



Matrix COSEC Devices API User Guide 

71 



S EC UR I TY  SO L UT I ON S 

#### **MATRIX COMSEC PVT. LTD.** 

###### **Corporate Office:** 

394-GIDC, Makarpura, Vadodara - 390010, India. Ph.:+91 265 2630555, Fax: +91 265 2636598 E-mail: Info@MatrixComSec.com 

###### **Manufacturing Unit:** 

15 & 19 GIDC, Waghodia - 391760, Dist. Vadodara, India. Ph.: +91 2668 263172/73 

###### **Customer Care:** 

Ph.: +91 265 2630555 E-mail: Customer.Care@MatrixComSec.com, <u>Support@MatrixComSec.com</u> 

<u>www.MatrixComSec.com</u> 

