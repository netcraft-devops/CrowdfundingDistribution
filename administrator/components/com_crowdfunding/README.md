Crowdfunding Platform for Joomla!
==========================
( Version 2.4 )
- - -

Crowdfunding is a platform that provides functionality for creating collective funding websites, powered by Joomla! CMS.

##Documentation
You can find documentation on following pages.

[Documentation and FAQ] (http://itprism.com/help/95-crowdfunding-documentation-faq)

[Quick start guide] (http://itprism.com/help/119-crowdfunding-step-by-step)

[Developers Guide] (http://itprism.com/help/120-crowdfunding-developers-documentation)

[API documentation] (http://cdn.itprism.com/api/crowdfunding/index.html)

##Download
You can [download Crowdfunding package] (http://itprism.com/free-joomla-extensions/ecommerce-gamification/crowdfunding-collective-raising-capital) and all payment plugins from the website of ITPrism.

##License
Crowdfunding Platform is under [GPLv3 license] (http://www.gnu.org/licenses/gpl-3.0.en.html).

## About the code in this repository
This repository contains code that you should use to create a package. You will be able to install that package via [Joomla extension manager] (https://docs.joomla.org/Help25:Extensions_Extension_Manager_Install).

##How to create a package?
* You should install [ANT] (http://ant.apache.org/) on your PC.
* Download or clone [Crowdfunding Platform distribution] (https://github.com/ITPrism/CrowdfundingDistribution).
* Download or clone the code from this repository.
* Rename the file __build/example.txt__ to __build/antconfig_j3.txt__.
* Edit the file __build/antconfig_j3.txt__. Enter name and version of your package. Enter the folder where the source code is (Social Community distribution). Enter the folder where the source code of the package will be stored (the folder where you have saved this repository).
* Save the file __build/antconfig_j3.txt__.
* Open a console and go in folder __build__.
* Type "__ant__" and click enter. The system will copy all files from distribution to the folder where you are going to build an installable package.

##Contribute
If you would like to contribute to the project you should use Crowdfunding Platform distribution. That repository provides Joomla CMS + Crowdfunding Platform.
You can clone it on your PC and install it on your localhost. You should use it as development environment. You should use it to create branches, to add new features, to fix issues and to send pull request.
