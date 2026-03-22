//Responsive de la navbar pour adaptation mobile
const hamburger = document.getElementById("hamburger");
const navLinks = document.getElementById("navLinks");
hamburger.addEventListener("click", ()=> {
    navLinks.classList.toggle("open");
});

//Detection automatique a l'acran
const haut = document.querySelectorAll(".haut");
const bas = document.querySelectorAll(".bas");
const gauche = document.querySelectorAll(".gauche");
const droit = document.querySelectorAll(".droit");
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {

            entry.target.classList.add("active");
        }
        else {
            //active le replay a chaque scroll
            entry.target.classList.remove("active");
        }
    });
}, {
    threshold: 0.2
});

haut.forEach(el =>
observer.observe(el)
);
bas.forEach(el =>
observer.observe(el)
);
gauche.forEach(el =>
observer.observe(el)
);
droit.forEach(el =>
observer.observe(el)
);

//description services "en savoir plus"

const services = {

    installation: {
        title: "Installation Électrique",
        subtitle: "Des installations fiables et sécurisées",
        description: "Nous réalisons l’installation complète de systèmes électriques pour les maisons les bureaux et les bâtiments professionnels. De l’étude du projet à la mise en service, nous garantissons des installations conformes aux normes de sécurité en vigueur. Notre objectif est de vous fournir un réseau électrique performant, fiable et durable, parfaitement adapté à vos besoins",
        image: "images/annexe (11).jpg",
        benefits: [
            "Installation conforme aux normes",
            "Sécurité garantie",
            "Matériel de qualité"
        ]
    },

    depannage: {
        title: "Dépannage Électrique",
        subtitle: "Intervention rapide et efficace",
        description: "En cas de panne électrique, notre équipe intervient rapidement pour identifier et résoudre le problème. Qu’il s’agisse d’un court-circuit, d’une coupure ou d’un dysfonctionnement d’équipement, nous mettons tout en œuvre pour rétablir votre installation en toute sécurité. Nous proposons des solutions efficaces pour éviter les pannes répétitives.",
        image: "images/annexe (3).jpg",
        benefits: [
            "Intervention rapide",
            "Diagnostic précis",
            "Solution durable"
        ]
    },

    cablage: {
        title: "Câblage de Maison",
        subtitle: "Un réseau électrique optimisé",
        description: "Nous assurons la conception et la mise en place du câblage électrique pour les nouvelles constructions et les rénovations. Chaque installation est pensée pour optimiser la distribution de l’énergie, garantir la sécurité et faciliter l’utilisation des équipements électriques au quotidien.",
        image: "images/cablage bati c.webp",
        benefits: [
            "Câblage sécurisé",
            "Installation moderne",
            "Durabilité"
        ]
    },

    maintenance: {
        title: "Maintenance Électrique",
        subtitle: "Un entretient régulier pour un système fonctionnel h24",
        description: "Nous proposons des services de maintenance électrique pour prévenir les pannes et assurer le bon fonctionnement de vos installations. Grâce à des contrôles réguliers et des interventions ciblées, nous prolongeons la durée de vie de vos équipements et garantissons votre sécurité.",
        image: "images/annexe (4).jpg",
        benefits: [
            "Optimisation des performances",
            "Inspection du système",
            "Révision saisonnière "
        ]
    },

    tableau: {
        title: "Tableau Électrique",
        subtitle: "Tableau électrique pour plus de sécurité",
        description: "Le tableau électrique est le cœur de votre installation. Nous réalisons l’installation, la mise à niveau et le remplacement de tableaux électriques afin d’assurer une distribution efficace et sécurisée du courant. Nos solutions permettent de protéger vos équipements et d’éviter les risques liés aux surtensions ou courts-circuits.",
        image: "images/tableau elec.webp",
        benefits: [
            "Conception du système",
            "Mise à niveau",
            "Remplacement des tableaux"
        ]
    }

};

const params = new URLSearchParams(window.location.search);
const serviceKey = params.get("service");

const service = services[serviceKey];

if(service){
    document.getElementById("service-title").textContent = service.title;
    document.getElementById("service-subtitle").textContent = service.subtitle;
    document.getElementById("service-description").textContent = service.description;
    document.getElementById("service-image").src = service.image;

    const benefitsContainer = document.getElementById("service-benefits");

    service.benefits.forEach(item => {
        const div = document.createElement("div");
        div.classList.add("benefit");
        div.textContent = item;
        benefitsContainer.appendChild(div);
    });
}
