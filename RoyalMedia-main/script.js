// Loader Logic for Landing Page
document.addEventListener("DOMContentLoaded", () => {
  const progressBar = document.getElementById("progress");
  const percentageText = document.getElementById("percentage");

  if (progressBar && percentageText) {
    let progress = 50;
    const interval = setInterval(() => {
      progress += Math.random() * 2; // Random increment for "realistic" feel
      if (progress >= 100) {
        progress = 100;
        clearInterval(interval);
        setTimeout(() => {
          window.location.href = "dashboard.html";
        }, 500);
      }
      progressBar.style.width = `${progress}%`;
      percentageText.textContent = `${Math.floor(progress)}%`;
    }, 100);
  }

  // 3D Dropdown Particles for Landing Page
  if (document.body.classList.contains("login-page") && typeof tsParticles !== 'undefined') {
    tsParticles.load("tsparticles", {
      particles: {
        number: { value: 100, density: { enable: true, value_area: 800 } },
        color: { value: ["#f4b400", "#ffffff", "#ffcc33"] },
        shape: { type: "circle" },
        opacity: {
          value: 0.5,
          random: true,
          anim: { enable: true, speed: 1, opacity_min: 0.1, sync: false }
        },
        size: {
          value: { min: 1, max: 4 },
          random: true,
        },
        move: {
          enable: true,
          speed: 3,
          direction: "bottom", // "Dropdown" effect
          random: true,
          straight: false,
          out_mode: "out",
          bounce: false,
        },
        rotate: {
          value: 0,
          random: true,
          direction: "clockwise",
          animation: { enable: true, speed: 5, sync: false }
        }
      },
      interactivity: {
        detect_on: "canvas",
        events: {
          onhover: { enable: true, mode: "bubble" },
          onclick: { enable: true, mode: "push" },
          resize: true
        },
        modes: {
          bubble: { distance: 200, size: 6, duration: 0.3, opacity: 1, speed: 3 },
          push: { particles_nb: 4 }
        }
      },
      retina_detect: true
    });
  }
});

// Particles Configuration for Dashboard (optimized for 3D feel)
if (document.getElementById('tsparticles') && !document.body.classList.contains("login-page")) {
  tsParticles.load("tsparticles", {
    particles: {
      number: { value: 60, density: { enable: true, value_area: 800 } },
      color: { value: "#f4b400" },
      shape: { type: "circle" },
      opacity: {
        value: 0.4,
        random: true,
      },
      size: {
        value: { min: 1, max: 3 },
        random: true,
      },
      links: {
        enable: true,
        distance: 150,
        color: "#f4b400",
        opacity: 0.3,
        width: 1
      },
      move: {
        enable: true,
        speed: 1,
        direction: "none",
        random: true,
        straight: false,
        out_mode: "out",
        bounce: false,
      }
    },
    interactivity: {
      detect_on: "window",
      events: {
        onhover: { enable: true, mode: "grab" },
        resize: true
      }
    }
  });
}

// Download function for app files
function downloadApp(url) {
  const link = document.createElement('a');
  link.href = url;
  link.download = 'PlayRob_1.0.apk';
  link.target = '_blank';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Download via Downloader App function
function downloadViaApp(url) {
  window.open(url, '_blank');
}

// Logout function (redirects back to loader page)
function logout() {
  window.location.href = "index.html";
}
