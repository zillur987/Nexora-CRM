<script setup>
import PageProgress from './components/PageProgress.vue';
import CustomCursor from './components/CustomCursor.vue';
import AppHeader from './components/AppHeader.vue';
import HeroSection from './components/HeroSection.vue';
import IntroSection from './components/IntroSection.vue';
import ProjectsShowcase from './components/ProjectsShowcase.vue';
import SpotlightSlider from './components/SpotlightSlider.vue';
import ResidenceTypes from './components/ResidenceTypes.vue';
import MarqueeStrip from './components/MarqueeStrip.vue';
import CommunitiesSection from './components/CommunitiesSection.vue';
import GalleryCarousel from './components/GalleryCarousel.vue';
import WhySection from './components/WhySection.vue';
import PaymentCalculator from './components/PaymentCalculator.vue';
import JourneySection from './components/JourneySection.vue';
import InsightsSection from './components/InsightsSection.vue';
import ClientStories from './components/ClientStories.vue';
import SiteFooter from './components/SiteFooter.vue';
</script>

<template>
  <a class="skip-link" href="#main">Skip to content</a>
  <PageProgress />
  <CustomCursor />

  <AppHeader />

  <main id="main">
    <HeroSection />
    <IntroSection />
    <ProjectsShowcase :autoplay="2500" />
    <SpotlightSlider :interval="7000" />
    <ResidenceTypes />
    <MarqueeStrip />
    <CommunitiesSection />
    <GalleryCarousel />
    <WhySection />
    <PaymentCalculator />
    <JourneySection />
    <InsightsSection />
    <ClientStories :interval="8000" />
  </main>

  <SiteFooter />
</template>
