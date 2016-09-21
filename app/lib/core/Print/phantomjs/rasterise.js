"use strict";
var page = require('webpage').create(),
    system = require('system'),
    address, output, size, pageWidth, pageHeight;

if (system.args.length < 3 || system.args.length > 9) {
    console.log('Usage: rasterize.js URL filename [paperwidth*paperheight|paperformat] [orientation] [margin-top] [margin-right] [margin-bottom] [margin-left]');
    console.log('  paper (pdf output) examples: "5in*7.5in", "10cm*20cm", "A4", "Letter"');
    phantom.exit(1);
} else {
    address = system.args[1];
    output = system.args[2];
    
    var m;
    
    page.paperSize = {
    	header: null,
    	footer: null,
        format: system.args[3],
        orientation: system.args[4],
        margin: m
    };
    
    if (system.args.length < 6) { m = "0mm"; }
    if (system.args.length == 6) { m = system.args[5]; }
    if (system.args.length > 6) { m = { "top": system.args[5], "right": system.args[6], "bottom": system.args[7], "left": system.args[8] }; }
    
    if (system.args.length > 3 && system.args[2].substr(-4) === ".pdf") {
        size = system.args[3].split('*');
        page.paperSize = size.length === 2 ? { width: size[0], height: size[1], margin: '0px' }
                                           : { format: system.args[3], orientation: 'portrait', margin: '1cm' };
    } else if (system.args.length > 3 && system.args[3].substr(-2) === "px") {
        size = system.args[3].split('*');
        if (size.length === 2) {
            pageWidth = parseInt(size[0], 10);
            pageHeight = parseInt(size[1], 10);
            page.viewportSize = { width: pageWidth, height: pageHeight };
            page.clipRect = { top: 0, left: 0, width: pageWidth, height: pageHeight };
        } else {
            pageWidth = parseInt(system.args[3], 10);
            pageHeight = parseInt(pageWidth * 3/4, 10); // it's as good an assumption as any
            page.viewportSize = { width: pageWidth, height: pageHeight };
        }
    }
    if (system.args.length > 4) {
        page.zoomFactor = system.args[4];
    }
    page.open(address, function (status) {
        if (status !== 'success') {
            console.log('Unable to load the address!');
            phantom.exit(1);
        } else {
        	if (page.evaluate(function(){return typeof PhantomJSPrinting == "object";})) {
                var paperSize = { 
                	header: {},
                	footer: {},
                	format: system.args[3],
					orientation: system.args[4],
					margin: m                
                }; 
                
                paperSize.header.height = page.evaluate(function() {
                    return PhantomJSPrinting.header ? PhantomJSPrinting.header.height : "0px";
                });
                paperSize.header.contents = phantom.callback(function(pageNum, numPages) {
                	return page.evaluate(function(pageNum, numPages) { return PhantomJSPrinting.header.contents(pageNum, numPages); }, pageNum, numPages);
                });
                paperSize.footer.height = page.evaluate(function() {
                    return PhantomJSPrinting.footer ? PhantomJSPrinting.footer.height : "0px";
                });
                paperSize.footer.contents = phantom.callback(function(pageNum, numPages) {
                	return page.evaluate(function(pageNum, numPages) { return PhantomJSPrinting.footer.contents(pageNum, numPages); }, pageNum, numPages);
                });
                page.paperSize = paperSize;
            }
            window.setTimeout(function () {
                page.render(output);
                phantom.exit();
            }, 200);
        }
    });
}