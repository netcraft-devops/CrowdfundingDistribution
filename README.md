Crowdfunding Platform Distribution
==========================
( Version 2.4 )
- - -

Distribution of [Crowdfunding Platform] (http://itprism.com/free-joomla-extensions/ecommerce-gamification/crowdfunding-collective-raising-capital) installed on Joomla! 3.5. It should be used as development environment where everyone can contribute a code to the project.

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

##How to install the distribution and contribute a code?
If you would like to add new feature to the extension or you would like to fix an issue, you should send pull request. How to do it?

* [Fork] (https://help.github.com/articles/fork-a-repo/) this repository. That will create a copy in your GitHub account.
* [Clone the repository] (https://help.github.com/articles/cloning-a-repository/), that you have just forked, on your PC .
* [Install the distribution like Joomla!] (https://docs.joomla.org/J3.x:Installing_Joomla) on your localhost. Note: You should not remove the folder 'installation' on the last step of the installation process.
* The installer will remove some files (joomla.xml, robots.txt.dist). You have to [revert the files] (https://www.quora.com/How-can-I-recover-a-file-I-deleted-in-my-local-repo-from-the-remote-repo-in-Git).
* [Create branch] (https://git-scm.com/book/en/v2/Git-Branching-Basic-Branching-and-Merging) and write your code.
* When you are done, [push your branch] (https://help.github.com/articles/pushing-to-a-remote/) to your remote (forked) repository.
* Go to your repository and [create pull request] (https://help.github.com/articles/using-pull-requests/).

##Branches
There are two general branches - __master__ and __develop__. The master branch contains the stable code. The "develop" is a branch where we will merge all pull requests. You should use the "develop" branch as development environment on your localhost. There will be "release" branches where we will prepare newest releases for publishing.

## How to create Crowdfunding Platform package?
If you would like to create a package that you will be able to install on your Joomla site, you should follow next steps.

* You should install [ANT] (http://ant.apache.org/) on your PC.
* Download or clone the code from this repository.
* Download or clone [Crowdfunding Platform package] (https://github.com/ITPrism/CrowdFunding).
* Rename the file __build/example.txt__ to __build/antconfig_j3.txt__.
* Edit the file __build/antconfig_j3.txt__. Enter name and version of your package. Enter the folder where the source code is (Crowdfunding Platform distribution). Enter the folder where the source code of the package will be stored (the folder where you have saved this repository).
* Save the file __build/antconfig_j3.txt__.
* Open a console and go in folder __build__.
* Type "__ant__" and click enter. The system will copy all files from distribution to the folder where you are going to build the installable package.