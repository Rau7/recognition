/**
 * Recognition wall JavaScript.
 *
 * @module     local_recognition/main
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/ajax", "core/notification"], function (
  $,
  Ajax,
  Notification
) {
  return {
    init: function () {
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

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "like",
              recordid: recordId,
              type: "like",
            },
            done: function (response) {
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
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Thanks button click handler
      $(document).on("click", ".recognition-thanks-btn", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "thanks",
              recordid: recordId,
              type: "thanks",
            },
            done: function (response) {
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
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Celebration button click handler
      $(document).on("click", ".recognition-celebration-btn", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "celebration",
              recordid: recordId,
              type: "celebration",
            },
            done: function (response) {
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
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
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

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "add_comment",
              recordid: recordId,
              type: "comment",
              content: content,
            },
            done: function (response) {
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
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Helper function to load comments
      function loadComments(recordId, commentsSection, btn) {
        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "get_comments",
              recordid: recordId,
              type: "comment",
            },
            done: function (response) {
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
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
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
          var page = pageMatch ? pageMatch[1] : 0;

          loadPostsPage(page);
        }
      );

      // Function to load posts via AJAX
      function loadPostsPage(page) {
        // Show loading indicator
        $("#recognition-loading").show();

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
              .find("#recognition-posts-container")
              .html();
            var paginationHtml = $(response)
              .find("#recognition-pagination")
              .html();

            // Update the page content
            $("#recognition-posts-container").html(postsHtml);
            $("#recognition-pagination").html(paginationHtml);

            // Hide loading indicator
            $("#recognition-loading").hide();

            // Scroll to top of posts container
            $("html, body").animate(
              {
                scrollTop: $("#recognition-posts-container").offset().top - 100,
              },
              500
            );
          },
          error: function (xhr, status, error) {
            console.error("AJAX error:", error);
            Notification.addNotification({
              message: "Error loading posts: " + error,
              type: "error",
            });
            $("#recognition-loading").hide();
          },
        });
      }
    },
  };
});
