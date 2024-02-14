$(function () {
  "use strict";

  var ajaxRequestInterval = 0;
  var setTime = 200;
  var sleepTime = 400;
  var abort_request_time = 40000;
  var abort_ajax_request = {abort: function() {}};

  // Form
  var contactForm = function () {
    if ($("#contactForm").length > 0) {
      $("#contactForm").validate({
        rules: {
          project_number: {
            required: true,
          },
          unit_code: {
            required: false,
          },
          authentication_code: {
            required: true
          },
        },
        messages: {
          unit_code: "Please enter project number",
          unit_code: "Please enter unit code",
          authentication_code: "Please enter a authentication code",
        },

        /* submit via ajax */
          submitHandler: function (form) {
            ajaxRequest(form);
          },
      });
    }
  };

  var ajaxRequest = function (form) {
      new Promise((resolve, reject) => {
          runAjaxRequest(form);
      }).then(function() {
        reSetInterval(form);
      });
  };

  function runAjaxRequest(form) {
    if (localStorage.getItem('login-time') != login_time) {
			window.location.href = 'index.php';
		}

    abort_ajax_request = $.ajax({
      type: "POST",
      url: "send-request.php",
      data: $(form).serialize(),
      sync: true,
      timeout: abort_request_time,
      beforeSend: function () {$(".submitting").css("display", "block").text("Submitting...")},
      success: function (response) {
        response = JSON.parse(response);
        let ele = "load-messages";
        let elements_count = $('body').find('[name="authentication_code[]"]').length;

        response.forEach(function(row) {
            let message = row.details.message || row.details || '';
            if (message === 'الوحدة المحددة غير متاحة للحجز.' || message === 'تم حجز الوحدة من قبل') {
                $(".submitting").css("display", "block").text("Resending In Moments...");
                reSetInterval(form, setTime);

            } else if (message === 'تم حجز الوحدة بنجاح.') {
                $(".submitting").css("display", "block").text("Resending In Moments...");
                ele = "load-success";
                // reSetInterval(form, setTime);
                
                if (elements_count > 1) reSetInterval(form);
                else reSetInterval(form, setTime);

            } else if (message == 'كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)') {
                //Clear Ajax Request Interval
                if (elements_count < 2) clearInterval(ajaxRequestInterval);
                $(".submitting").css("display", "none");
                
            } else if (message == 'goto login') {
              window.location.href = 'index.php';
            } else {
                if (row.details.status == 200) ele = "load-success";
                reSetInterval(form);
            }
            
            if (message !== '' && ! message.includes('Fatal error'))  showMessage(message, ele);
            console.log(message);
            
        });

        console.log(`Request Will Send After : ${setTime}`);
      },
      error: function (response) {
          console.log(response);
          reSetInterval(form);
          // $("#form-message-warning").html("Something went wrong. Please try again.");
          // showMessage('Something went wrong. Please try again.', 'color: #f00');
          // $("#form-message-warning").fadeIn();
          // $(".submitting").css("display", "none");
      },
    });
  }

  function reSetInterval(form, time = 200) {
      setTime = time;

      setTimeout(() => {
          async function waitUntil(form, time) {
              return await new Promise(resolve => {
                  const ajaxRequestInterval = setInterval(() => {
                        runAjaxRequest(form);
                        clearInterval(ajaxRequestInterval);
                  }, time);
              });
          }
          waitUntil(form, time);
      }, sleepTime);
      
  }

  function showMessage(msg, ele = "load-messages") {
      $(`#${ele}`).prepend(`<li style="padding: 10px 0; border-bottom: 1px solid #ddd; ">${msg}</li>`);
  }

  contactForm();

  $('#add-new-key').on('click', function() {
      let parent = $(this).parent();

      if (parent.find('.clone-row:last textarea').val() == '') {
          alert("يرجي ملئ جميع الحقول");
          return ;
      }

      parent.find('.clone-row:first').clone().appendTo( parent );
      parent.find('.clone-row:last textarea').val('').prop('readonly', false).prop('disabled', false);
  });

  $('body').on('click', '.clone-row .remove-clone', function() {
      if ( $('body').find('.clone-row').length == 1 ) {
          alert("لا يمكن حذف جميع الحقول");
          return ;
      }
      $(this).parent().remove();
  });

  $('body').on('change', '.clone-row textarea', function() {
      let ele = $(this);

      $("body").find(".clone-row textarea").not($(this)).each(function() {
          if (ele.val() == $(this).val()) {
            ele.val('');
            alert("هذا التوكن مستخدم مسبقا");
          }
      });
  });

  $(document).keydown(function(e) {
      if (e.keyCode == 82 && e.ctrlKey) {
        abort_ajax_request.abort();
      }
  });
});
