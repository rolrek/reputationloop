$.fn.paginate = function(settings) {
    //Globals
    this.book = new Array();
    this.current_page = 1;
    this.settings = settings;
    //A convenient way to refer to the top level parent function ($.fn.paginate/targeted jQuery element) while inside another function (say, this.constructor)
    var self = this;
    //Function used to show a particular page
    this.show_page = function(page_number) {
            //Set the page hash
            document.location.hash = page_number;
            //Empty the div that contains the items to be paged
            $(self).empty();
            //Load the page the user want's to see from the list of pages
            var page_content = self.book[page_number];
            //Loop through the page content
            $.each(page_content, function(i, current_page_excerpt) {
                //Run the jQuery show() function on the current_page_excerpt element
                $(current_page_excerpt).show();
                //Add the current_page_excerpt to the targeted element
                $(self).append(current_page_excerpt);
            });
            //Remove any existing #pagination_container element
            $(".pagination_container").remove();
            //Create the pagination_container
            self.pagination_container = $("<div>").attr("class", "pagination_container").css({
                "width": "98%",
                "float": "left",
                "clear": "both",
                "margin-top": "4px",
                "margin-top": "4px",
                "display": "inline"
            });
            //Use slider if requested
            if (self.settings.slider == true) {
                //Something
                $(self.pagination_container).removeAttr("pagination_container");
                //Create the pagination indicator
                self.pagination_indicator = $("<div>").attr("class", "pagination_container").text(page_number + " of " + (self.book.length - 1)).css({
                    "width": "99%",
                    "clear": "both",
                    "margin": "2px",
                    "float": "left",
                    "display": "inline",
                    "text-align": "right",
                    "font-weight": "bold"
                });
                //Create the slider
                var slider = $("<div>").addClass("slider").slider({
                    "min": 1,
                    "range": "max",
                    "value": page_number,
                    "max": (self.book.length - 1),
                    "slide": function(ui, slider) {
                        //Set the page hash
                        document.location.hash = slider.value;
                        //Set the label
                        $(self.pagination_indicator).text(slider.value + " of " + (self.book.length - 1));
                    }
                });
                //Attach mouseup event
                $(slider).mouseup(function() {
                    //Go to the current page
                    self.show_page(document.location.hash.replace("#", ""));
                });
                //Attach pagination_indicator to the top of the paged div
                $(self.pagination_indicator).insertBefore(
                    $(self).children().first()
                );
                //Attach slider to pagination_container if it isn't already there AND the total amount of pages is more than 1
                if ((!$(self.pagination_container).find(".slider")[0]) && ((self.book.length - 1) > 1)) {
                    $(self.pagination_container).append(slider);
                }
            }
            else {
                //Loop through the entire book to create the pagination elements
                for (i in self.book) {
                    //Create the page number
                    var page_number_element = $("<div>").text(i).attr({
                        "page_number": i,
                        "class": "excerpt_pagination"
                    });
                    //Style it
                    $(page_number_element).css({
                        "float": "left",
                        "margin": "12px",
                        "padding": "4px",
                        "display": "inline",
                        "cursor": "pointer",
                        "border-bottom": "solid 2px rgb(0,0,0)"
                    });
                    //Highlight if the current page matches the requested page number
                    if (i == page_number) {
                        $(page_number_element).css("font-weight", "bold");
                    }
                    //Add the click event
                    $(page_number_element).click(function() {
                        //Go to the current page
                        self.show_page($(this).attr("page_number"));
                    });
                   //Only show up to Page 20
                    if (i <= 20) {
                        //Add the page number
                        $(self.pagination_container).append(page_number_element);
                    }
                }
                //If there are more than 20 pages, add a convenient little drop down
                if (self.book.length >= 20) {
                    var pageNumberDropdown = $("<select>").change(function() {
                        self.show_page($(this).val());
                    });
                    //Style it
                    $(pageNumberDropdown).css({
                        "float": "left",
                        "height":"25px",
                        "padding": "0px",
                        "display": "inline",
                        "cursor": "pointer",
                        "margin-top": "13px",
                        "border-bottom": "solid 2px rgb(0,0,0)"
                    });
                        //Add the pageNumber options
                        for(i = 21; i < self.book.length; i++){
                            $(pageNumberDropdown).append(
                                $("<option>").attr("value",i).text(i)
                            );
                        }
                    $(self.pagination_container).append(pageNumberDropdown);
                }

            }
            //And then, when it is done, you have my permission
            $(self.pagination_container).insertAfter(
                $(self).children().last()
            );
            //Highlight the current page number
            $(".pagination_container select").val(page_number);
            $(".excerpt_pagination[page_number='" + page_number + "']").css("font-weight", "bold");            
        }
        //Constructor function
    this.constructor = function() {
            $(self).children().each(function() {
                //Except script elements, because fuck script elements. That's why
                //But really, they ruin the pagination since they aren't visible content
                if ($(this).is("script")) {
                    return;
                }
                //If the current page doesn't exist in the book (because we moved to the next page) ...
                if (!self.book[self.current_page]) {
                    //...then create the page
                    self.book[self.current_page] = [];
                }
                else {
                    //Otherwise the page exists, so let's see if we've maxed out the items per page limit...
                    if ((self.book[self.current_page].length % self.settings.items_per_page) == 0) {
                        //..Indeed we have, so we move to the next page...
                        self.current_page++;
                        self.book[self.current_page] = [];
                    }
                }
                //Hide the current element
                $(this).hide();
                //At this point we should be on the right page, so we'll add the current element into that page.
                self.book[self.current_page].push(this);
            });
            //Finally, get the show on the road
            if (document.location.hash) {
                self.show_page(document.location.hash.split("#").join(""));
            }
            else {
                self.show_page(1);
            }
        }
        //Run the constructor
    this.constructor();
}