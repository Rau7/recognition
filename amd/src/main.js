/**
 * Recognition wall JavaScript.
 *
 * @module     local_recognition/main
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/notification"], function ($, Notification) {
  return {
    init: function () {
      // Mention/autocomplete for textarea
      $(document).on("input", ".mention-textarea", function () {
        console.log("hey");
        const $textarea = $(this);
        const cursorPos = $textarea.prop("selectionStart");
        const text = $textarea.val();
        const lastAt = text.lastIndexOf("@", cursorPos - 1);

        console.log("[mention] textarea value:", text);
        console.log("[mention] cursorPos:", cursorPos, "lastAt:", lastAt);

        if (
          lastAt >= 0 &&
          (lastAt === 0 || /\s/.test(text.charAt(lastAt - 1)))
        ) {
          const query = text.substring(lastAt + 1, cursorPos);
          console.log("[mention] @ bulundu, query:", query);

          if (query.length >= 1) {
            console.log("[mention] AJAX tetikleniyor, query:", query);
            $.ajax({
              url: M.cfg.wwwroot + "/local/recognition/ajax.php",
              method: "GET",
              data: {
                action: "searchusers",
                query: query,
                sesskey: M.cfg.sesskey,
              },
              dataType: "json",
              success: function (res) {
                console.log("[mention] AJAX success, response:", res);
                if (res.success) {
                  let $dropdown = $("#mention-dropdown");
                  if ($dropdown.length === 0) {
                    $dropdown = $(
                      '<ul id="mention-dropdown" class="mention-dropdown"></ul>'
                    ).appendTo("body");
                  }

                  $dropdown.empty();
                  (res.data || []).forEach(function (user) {
                    const $item = $('<li class="mention-item"></li>')
                      .text(user.fullname)
                      .attr("data-uid", user.id);
                    $dropdown.append($item);
                  });

                  const offset = $textarea.offset();
                  $dropdown.css({
                    top: offset.top + $textarea.outerHeight(),
                    left: offset.left,
                    width: $textarea.outerWidth(),
                    position: "absolute",
                    zIndex: 9999,
                    display: "block",
                  });
                  console.log(
                    "[mention] Dropdown gösterildi, user count:",
                    res.users.length
                  );
                } else {
                  console.log(
                    "[mention] AJAX success fakat success=false, dropdown gizlendi"
                  );
                  $("#mention-dropdown").hide();
                }
              },
              error: function (xhr, status, error) {
                console.log("[mention] AJAX error", xhr, status, error);
                $("#mention-dropdown").hide();
              },
            });
          } else {
            console.log("[mention] Query boş, dropdown gizlendi");
            $("#mention-dropdown").hide();
          }
        } else {
          console.log("[mention] @ uygun yerde değil, dropdown gizlendi");
          $("#mention-dropdown").hide();
        }
      });

      // Kullanıcı mention seçtiğinde textarea'ya ekleme
      $(document).on("mousedown", ".mention-item", function (e) {
        e.preventDefault(); // Blur tetiklenmesin
        const $item = $(this);
        const $textarea = $(".mention-textarea:focus");
        if ($textarea.length === 0) return;

        const cursorPos = $textarea.prop("selectionStart");
        const text = $textarea.val();
        const lastAt = text.lastIndexOf("@", cursorPos - 1);

        const before = text.substring(0, lastAt);
        const after = text.substring(cursorPos);
        const mentionText = "@" + $item.text() + " ";

        $textarea.val(before + mentionText + after);
        $textarea.focus();
        $textarea[0].setSelectionRange(
          (before + mentionText).length,
          (before + mentionText).length
        );
        $("#mention-dropdown").hide();
      });

      // Textarea odaktan çıkınca dropdown'u gizle (hafif gecikmeyle)
      $(document).on("blur", ".mention-textarea", function () {
        setTimeout(function () {
          $("#mention-dropdown").hide();
        }, 200);
      });
      // File upload preview
      $(".file-input").on("change", function (e) {
        var file = e.target.files[0];
        if (!file) return;

        // Check file size
        var maxSize = $(this).data("max-size");
        if (file.size > maxSize) {
          Notification.addNotification({
            message: "File size must be less than 5MB",
            type: "error",
          });
          $(this).val("");
          return;
        }

        // Show preview
        var reader = new FileReader();
        reader.onload = function (e) {
          var $preview = $(".post-attachments");
          $preview
            .html(
              `
            <div class="attachment-preview">
              <img src="${e.target.result}" alt="Preview">
              <div class="attachment-remove">&times;</div>
            </div>
          `
            )
            .show();
        };
        reader.readAsDataURL(file);
      });

      // Remove attachment preview
      $(document).on("click", ".attachment-remove", function () {
        $(".file-input").val("");
        $(".post-attachments").empty().hide();
      });

      // Like button click handler
      $(document).on("click", ".recognition-like-btn", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        console.log("Like button clicked for record ID:", recordId);

        // Use the ajax.php file directly instead of the web service
        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "like",
            postid: recordId,
            content: "",
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            console.log("Like response:", response);
            if (response.success) {
              var likesCount = response.data.likes;
              var isLiked = response.data.isLiked;

              btn.find(".likes-count").text(likesCount);
              btn.toggleClass("liked", isLiked);
            } else {
              console.error("Like error:", response.message);
              Notification.addNotification({
                message: response.message || "Error liking post",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", xhr.responseText, status, error);
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      });

      // Thanks button click handler
      $(document).on("click", ".recognition-thanks-btn", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        console.log("Thanks button clicked for record ID:", recordId);

        // Use the ajax.php file directly instead of the web service
        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "thanks",
            postid: recordId,
            content: "",
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            console.log("Thanks response:", response);
            if (response.success) {
              var thanksCount = response.data.thanks;
              var isThanked = response.data.isThanked;

              btn.find(".thanks-count").text(thanksCount);
              btn.toggleClass("thanked", isThanked);
            } else {
              console.error("Thanks error:", response.message);
              Notification.addNotification({
                message: response.message || "Error thanking post",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", xhr.responseText, status, error);
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      });

      // Celebration button click handler
      $(document).on("click", ".recognition-celebration-btn", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        console.log("Celebration button clicked for record ID:", recordId);

        // Use the ajax.php file directly instead of the web service
        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "celebration",
            postid: recordId,
            content: "",
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            console.log("Celebration response:", response);
            if (response.success) {
              var celebrationCount = response.data.celebration;
              var isCelebrated = response.data.isCelebrated;

              btn.find(".celebration-count").text(celebrationCount);
              btn.toggleClass("celebrated", isCelebrated);
            } else {
              console.error("Celebration error:", response.message);
              Notification.addNotification({
                message: response.message || "Error celebrating post",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", xhr.responseText, status, error);
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      });

      // Comments button click handler
      $(document).on("click", ".recognition-comments-btn", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");
        var commentsSection = $("#comments-" + recordId);

        // Toggle comments visibility
        commentsSection.slideToggle();

        // Load comments if not already loaded
        if (commentsSection.find(".comments-list").length === 0) {
          commentsSection.prepend('<div class="comments-list"></div>');
          loadComments(recordId, commentsSection, btn);
        }
      });

      // Comment form submit handler
      $(document).on("submit", ".recognition-comment-form", function (e) {
        e.preventDefault();
        var form = $(this);
        var recordId = form.data("record-id");
        var input = form.find('input[name="content"]');
        var content = input.val().trim();
        var post = form.closest(".recognition-post");
        var commentsBtn = post.find(".recognition-comments-btn");
        var commentsSection = form.closest(".recognition-comments");

        if (!content) {
          return;
        }

        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "add_comment",
            postid: recordId,
            content: content,
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            console.log("Comment response:", response);
            if (response.success) {
              input.val("");
              loadComments(recordId, commentsSection, commentsBtn);
            } else {
              console.error("Comment error:", response.message);
              Notification.addNotification({
                message: response.message || "Error adding comment",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", xhr.responseText, status, error);
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      });

      // Helper function to load comments
      function loadComments(recordId, commentsSection, btn) {
        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "get_comments",
            postid: recordId,
            content: "",
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            console.log("Comments response:", response);
            if (response.success) {
              commentsSection.find(".comments-list").html(response.data.html);
              btn.find(".comments-count").text(response.data.count);
            } else {
              console.error("Comments error:", response.message);
              Notification.addNotification({
                message: response.message || "Error loading comments",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", xhr.responseText, status, error);
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      }

      // AJAX Pagination
      $(document).on(
        "click",
        "#recognition-pagination .page-item:not(.active) .page-link",
        function (e) {
          e.preventDefault();
          var pageUrl = $(this).attr("href");
          if (!pageUrl) {
            return;
          }

          // Extract page number from URL
          var pageMatch = pageUrl.match(/[?&]page=(\d+)/);
          var page = pageMatch ? parseInt(pageMatch[1]) : 0;

          loadPostsPage(page);
        }
      );

      // Function to load posts via AJAX
      function loadPostsPage(page) {
        // Show loading indicator
        $(".loading-indicator").show();

        // Get the current URL and update the browser history
        var baseUrl = window.location.href.split("?")[0];
        var newUrl = baseUrl + (page > 0 ? "?page=" + page : "");
        window.history.pushState({ page: page }, "", newUrl);

        // Make AJAX request to get posts
        $.ajax({
          url: baseUrl,
          data: {
            page: page,
            ajax: 1,
          },
          method: "GET",
          success: function (response) {
            // Extract posts container content from the response
            var postsHtml = $(response)
              .find(".recognition-posts-container")
              .html();

            // Update the page content
            $(".recognition-posts-container").html(postsHtml);

            // Update pagination active state
            $("#recognition-pagination .page-item").removeClass("active");

            // Fix the "Go to last page" button first
            var totalPages = parseInt(
              $("#recognition-pagination").data("total-pages") || 0
            );
            if (totalPages > 0) {
              var baseUrl = window.location.href.split("?")[0];
              var lastPageUrl = baseUrl + "?page=" + (totalPages - 1);
              $("#recognition-pagination .page-item.last .page-link").attr(
                "href",
                lastPageUrl
              );
            }

            // Now update active state for each link
            $("#recognition-pagination .page-item .page-link").each(
              function () {
                var linkUrl = $(this).attr("href");
                if (linkUrl) {
                  var linkPageMatch = linkUrl.match(/[?&]page=(\d+)/);
                  var linkPage = linkPageMatch ? parseInt(linkPageMatch[1]) : 0;

                  // Check if this is the current page
                  if (linkPage === page) {
                    $(this).parent(".page-item").addClass("active");
                  }
                } else {
                  // Handle the case for the "first page" link which might not have a page parameter
                  if (page === 0 && $(this).text().trim() === "1") {
                    $(this).parent(".page-item").addClass("active");
                  }
                }
              }
            );

            // Hide loading indicator
            $(".loading-indicator").hide();

            // Scroll to top of posts container
            $("html, body").animate(
              {
                scrollTop: $(".recognition-posts-container").offset().top - 100,
              },
              500
            );
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", xhr.responseText, status, error);
            Notification.addNotification({
              message: "Error loading posts: " + error,
              type: "error",
            });
            $(".loading-indicator").hide();
          },
        });
      }
    },
  };
});
