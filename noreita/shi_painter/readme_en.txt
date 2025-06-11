

This document is available in complete HTML form here:
http://www.hokuten.net/howkaki/shipainter.html



English Documentation for Oekaki Shi-Painter
by potato (http://www.hokuten.net/)
Free for distribution and amendment, but please do not erase anything.

Oekaki Shi-Painter
Copyright (c)2000-2005 shi-chan (http://shichan.jp)

Software requirements:
	Netscape 4.7, Mozilla Firefox, MSIE 5+, or Java compatible browser

Package:
	res/
		-> bn.gif
			Link button, also used in JavaScript example
		-> c1x16xy16y.gif
			Brush head
		-> res.txt, res_*.txt
			Localization
		-> normal.zip, pro.zip
			Java class files
		-> res_normal.zip, res_pro.zip
			Applet images, brushes
		-> tt.zip
			Textures
	-> spainter.jar
		Java applet
	-> Readme_Shichan.html
		Readme file (Japanese, partial English)
		REQUIRED FOR PACKAGE DISTRIBUTION
	-> spainter.html, spainter_pro.html
		Example applet usage
		
Description:
	The purpose of this applet is to create an image which may be sent to the server via POST action. The animation of the drawing and a thumbnail is also sent. The data may be saved as JPEG or PNG format. Server-side scripting, such as CGI, PHP, or ASP, is required to process the POST data.
	
Objectives:
	1. Easy to use applet, but powerful in the hands of an experienced artist.
	2. Able to draw images quickly using this applet.
	3. Quick data processing.
	4. Based on its highly successful predecessor, PaintBBS.
	5. Compatible with PaintChat3
	6. Uses system functions to facilitate Java operations.
	7. Multi-language support.
	
This applet can be used for web diaries, drawing, imaging, and various other functions. The possibilities are limited only to the users.
It is best suited for art because of the automatic compression-determination.
A server-side script to process the images is not included because many different scripts are already available.

Image Types:
	PNG, JPG, and PCH (SPCH) files may be produced with this applet.
	The PNG compression quality is quite good, and interlacing is available via the "image_interlace" parameter.
	JPEG compression quality is also better than many commercial applications, even at the lower settings there is little lossiness.
	The applet cannot determine which format to save images in, but that is left up to the server-side scripting.
	Saving images may take some time due to server lag and low system resources. Please do not interrupt the process in order to properly save your image.
	
Image Restoration:
	In case of accidental page refreshes or page loading, the applet stores a temporary image which can be restored as long as the same browser windows remains open. If you close the browser without saving, the image is lost forever.
	
Image Loading:
	Loading images as well as PCH animation files is possible.
	PCH animations load faster than in PaintBBS.
	
Security:
	IP addresses may be denied.
	A security timer may be added requiring a minimum drawing time before submission.
	A security click counter may be added requiring a minimum number of strokes before submission.

Interface Customization:
	Pen nibs, brushes, textures, and even interfaces may be customized and replaced.
	
Upgrading:
	To upgrade, simply replace spainter.jar, the res folder, and modify HTML parameters as in the example files.
	Other changes may be necessary depending on the script used.
	
Technical Information:

Problem:
	The applet loads the right image, but the browser shows the old image.
Solution:
	Refresh the page, you may need to empty the browser's cache.

Problem:
	spainter.jar does not exist even after uploading.
Solution:
	Make sure that your FTP client and server are not unpackaging the file and uploading the contents.
	JAR files are archives, you want to upload the archive itself, not the contents.
	
Problem:
	The image is not submitting.
Solution:
	Wait a while and the save image dialogue will disappear. Try again.
	If the image submits but is not saving, make sure the url_save parameter is correct in your applet page.
	Your save folder may not be properly CHMOD'ed.
	If your spainter.jar is in a different folder from the applet page, you may need to use an absolute URL (http://) instead of a local one.
	Also, refer to your script troubleshooting for more help.
	
Problem:
	The status bar says "c.ShiPainter not found."
Solution:
	Try putting your archive inside an absolute URL, e.g., <applet archive="http://foo.com/spainter.jar">
	If that doesn't work, make sure spainter.jar is uploaded properly.
	
Problem:
	The applet takes forever to load.
Solution:
	Large sized images with many colours will cause the applet to load slowly.
	You should reduce the canvas size and probably use JPEG format for saving.
	

POST Data Formatting:
'S'
	-> 1 byte
	-> You can change this using the "header_magic" parameter.
Header length
	-> 8 bytes
Header
	-> Variable length
	-> Depends on how many header parameters you included.
Image length
	-> 8 bytes
\r\n
	-> CR LF (Carriage return and line feed characters)
Image data
	-> Variable length
	-> PNG or JPEG format
Animation length
	-> 8 bytes
Animation data
	-> Variable length
	-> PCH format
Thumbnail length
	-> 8 bytes
Thumbnail data
	-> PNG or JPEG format

If you do not specify thumbnail_type2 in the parameters, the thumbnail is not submitted.

If you specify poo as true in the parameters, the POST data will consist of the following:
0x00000000
\r\n(CR LF)
Image data
	-> PNG or JPEG format
Animation length
	-> 8 bytes
Animation data
	-> Variable length
	-> PCH format
Thumbnail length
	-> 8 bytes
Thumbnail data
	-> PNG or JPEG format

The poo format is not recommended, but its use is determined by your choice of script.
Most scripts do not use the poo format.

The thumbnail size may be adjusted using the thumbnail_width and thumbnail_height parameters.
Percentages may be used as well. The animation data is sent regardless of whether or not this is specified.

The compression level can be changed with the compress_level parameter.

It is recommended that you allow image types be determined by the applet.
Images with many colors will save better as JPEG and those with few colors will have smaller file size as PNG.